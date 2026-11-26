<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Mail\WithdrawalStatusMail;
use App\Models\Country;
use App\Models\Kyc;
use App\Models\Role;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake();

        Country::create(['id' => 1, 'name' => 'Nigeria', 'iso3' => 'NGA', 'iso2' => 'NG', 'currency' => 'NGN', 'phone_code' => '+234']);
        Country::create(['id' => 2, 'name' => 'Germany', 'iso3' => 'DEU', 'iso2' => 'DE', 'currency' => 'EUR', 'phone_code' => '+49']);

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AddAdminAccount::class);

        $this->admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))->firstOrFail();
        $this->member = $this->createMember('member@example.com', 2);
        $this->member->wallet()->create(['balance' => 1000, 'currency' => 'EUR']);
        Kyc::create(['user_id' => $this->member->id, 'status' => 'approved']);
    }

    public function test_a_member_in_any_country_can_add_an_iban_account_without_a_payment_provider(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/v1/withdrawals', $this->ibanAccount())
            ->assertOk()
            ->assertJsonPath('data.account_type', 'iban')
            ->assertJsonPath('data.iban', 'DE89370400440532013000')
            ->assertJsonPath('data.country', 'DE')
            ->assertJsonPath('data.currency', 'EUR');

        Http::assertNothingSent();

        $this->getJson('/v1/withdrawals')->assertOk()->assertJsonPath('data.data.0.swift_code', 'COBADEFFXXX');
    }

    public function test_an_iban_account_needs_a_valid_iban_and_swift_code(): void
    {
        Sanctum::actingAs($this->member);

        $this->postJson('/v1/withdrawals', ['iban' => 'nope', 'swift_code' => null] + $this->ibanAccount())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iban', 'swift_code']);
    }

    public function test_withdrawing_creates_a_request_and_holds_the_money_without_paying_out(): void
    {
        $this->addAccount();
        Sanctum::actingAs($this->member);

        $this->postJson('/v1/withdrawals/withdraw', ['amount' => 400, 'pin' => '1234'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.account.iban', 'DE89370400440532013000');

        Http::assertNothingSent();
        $this->assertSame(600.0, (float) $this->member->wallet()->first()->balance);
        $this->assertDatabaseHas('transactions', ['type' => 'withdrawal', 'status' => 'pending', 'amount' => 400]);
        Mail::assertQueued(WithdrawalStatusMail::class, fn ($mail) => $mail->hasTo('member@example.com'));
    }

    public function test_a_member_cannot_hold_two_requests_or_overdraw(): void
    {
        $this->addAccount();
        Sanctum::actingAs($this->member);

        $this->postJson('/v1/withdrawals/withdraw', ['amount' => 5000, 'pin' => '1234'])
            ->assertStatus(400)->assertJsonPath('message', 'Insufficient wallet balance');

        $this->postJson('/v1/withdrawals/withdraw', ['amount' => 100, 'pin' => '1234'])->assertCreated();
        $this->postJson('/v1/withdrawals/withdraw', ['amount' => 100, 'pin' => '1234'])
            ->assertStatus(400)->assertJsonPath('message', 'You already have a withdrawal request in progress.');
    }

    public function test_withdrawing_needs_approved_kyc(): void
    {
        $this->addAccount();
        $this->member->kyc()->update(['status' => 'pending']);
        Sanctum::actingAs($this->member);

        $this->postJson('/v1/withdrawals/withdraw', ['amount' => 100, 'pin' => '1234'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Please complete identity verification (KYC) before withdrawing.');
    }

    public function test_a_member_can_cancel_a_pending_request_and_get_the_money_back(): void
    {
        $withdrawal = $this->requestWithdrawal(300);
        Sanctum::actingAs($this->member);

        $this->postJson("/v1/withdrawals/requests/{$withdrawal->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1000.0, (float) $this->member->wallet()->first()->balance);
        $this->getJson('/v1/withdrawals/requests')->assertOk()->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_admin_approves_then_marks_paid_after_paying_by_hand(): void
    {
        $withdrawal = $this->requestWithdrawal(250);
        Sanctum::actingAs($this->admin);

        $this->getJson('/v1/admin/withdrawals?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.reference', $withdrawal->reference)
            ->assertJsonPath('data.0.user.email', 'member@example.com')
            ->assertJsonPath('summary.pending', 1);

        $this->postJson("/v1/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->postJson("/v1/admin/withdrawals/{$withdrawal->id}/mark-paid", [
            'payout_reference' => 'WIRE-2026-0042',
            'proof_url' => 'https://res.cloudinary.com/demo/receipt.pdf',
        ])->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payout_reference', 'WIRE-2026-0042');

        $this->assertSame(750.0, (float) $this->member->wallet()->first()->balance);
        $this->assertDatabaseHas('transactions', ['reference' => $withdrawal->reference, 'status' => 'completed']);
        $this->assertSame(250.0, (float) $this->member->wallet()->first()->withdrawal_total);
        $this->assertDatabaseHas('audits', ['user_id' => $this->member->id, 'action' => 'withdrawal.paid', 'performer_id' => $this->admin->id]);
        Http::assertNothingSent();
    }

    public function test_rejecting_returns_the_money_and_paid_requests_are_final(): void
    {
        $withdrawal = $this->requestWithdrawal(200);
        Sanctum::actingAs($this->admin);

        $this->postJson("/v1/admin/withdrawals/{$withdrawal->id}/reject")->assertStatus(422);

        $this->postJson("/v1/admin/withdrawals/{$withdrawal->id}/reject", ['reason' => 'Account name does not match'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame(1000.0, (float) $this->member->wallet()->first()->balance);
        $this->assertDatabaseHas('transactions', ['reference' => $withdrawal->reference, 'status' => 'failed']);

        $this->postJson("/v1/admin/withdrawals/{$withdrawal->id}/mark-paid", ['payout_reference' => 'X'])
            ->assertStatus(422);
    }

    public function test_members_cannot_use_admin_withdrawal_endpoints(): void
    {
        $withdrawal = $this->requestWithdrawal(100);
        Sanctum::actingAs($this->member);

        $this->getJson('/v1/admin/withdrawals')->assertForbidden();
        $this->postJson("/v1/admin/withdrawals/{$withdrawal->id}/mark-paid", ['payout_reference' => 'X'])->assertForbidden();
    }

    public function test_the_bank_account_cannot_change_while_a_withdrawal_is_in_progress(): void
    {
        $withdrawal = $this->requestWithdrawal(100);
        Sanctum::actingAs($this->member);

        $this->putJson("/v1/withdrawals/{$withdrawal->withdrawal_account_id}", $this->ibanAccount())
            ->assertStatus(400);
        $this->deleteJson("/v1/withdrawals/{$withdrawal->withdrawal_account_id}")->assertStatus(400);
    }

    /**
     * @return array<string, mixed>
     */
    private function ibanAccount(): array
    {
        return [
            'pin' => '1234',
            'account_type' => 'iban',
            'account_name' => 'Ada Obi',
            'bank_name' => 'Commerzbank',
            'iban' => 'DE89 3704 0044 0532 0130 00',
            'swift_code' => 'cobadeffxxx',
        ];
    }

    private function addAccount(): void
    {
        Sanctum::actingAs($this->member);
        $this->postJson('/v1/withdrawals', $this->ibanAccount())->assertOk();
    }

    private function requestWithdrawal(float $amount): WithdrawalRequest
    {
        $this->addAccount();
        $this->postJson('/v1/withdrawals/withdraw', ['amount' => $amount, 'pin' => '1234'])->assertCreated();

        return WithdrawalRequest::query()->latest()->firstOrFail();
    }

    private function createMember(string $email, int $countryId): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'pin' => Hash::make('1234'),
            'role_id' => Role::where('slug', 'user')->value('id'),
            'country_id' => $countryId,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }
}

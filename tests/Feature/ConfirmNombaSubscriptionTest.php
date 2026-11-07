<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfirmNombaSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Country::create([
            'id' => 1,
            'name' => 'Nigeria',
            'iso3' => 'NGA',
            'iso2' => 'NG',
            'currency' => 'NGN',
            'phone_code' => '+234',
        ]);

        Role::create([
            'id' => 1,
            'name' => 'Member',
            'slug' => 'member',
        ]);

        config([
            'nomba.mode' => 'test',
            'nomba.baseUrl' => 'https://sandbox.nomba.com',
            'nomba.clientId' => 'test-client',
            'nomba.clientSecret' => 'test-secret',
            'nomba.accountId' => 'acc-123',
        ]);
    }

    public function test_a_successful_nomba_order_activates_the_plan(): void
    {
        $user = $this->createMember('nomba-confirm@example.com');
        $plan = $this->createPlan();
        $orderId = 'ca218976-bcbe-4f07-9c07-1a75468ed6d5';

        $this->fakeNomba($this->transaction($user, $plan, $orderId, 5000, true));

        $this->artisan('plans:confirm-nomba', ['order' => $orderId])
            ->assertSuccessful();

        $this->assertSame($plan->id, $user->fresh()->currentPlan()?->id);
        $this->assertDatabaseHas('user_plans', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'nomba',
            'status' => 'active',
            'provider_subscription_id' => $orderId,
        ]);
    }

    public function test_an_unpaid_nomba_order_does_not_activate_a_plan(): void
    {
        $user = $this->createMember('nomba-unpaid@example.com');
        $plan = $this->createPlan();
        $orderId = 'ca218976-bcbe-4f07-9c07-1a75468ed6d5';

        $this->fakeNomba($this->transaction($user, $plan, $orderId, 5000, false));

        $this->artisan('plans:confirm-nomba', ['order' => $orderId])
            ->assertFailed();

        $this->assertNull($user->fresh()->currentPlan());
        $this->assertDatabaseCount('user_plans', 0);
    }

    public function test_a_paid_amount_changed_by_the_exchange_rate_still_activates_the_plan(): void
    {
        $user = $this->createMember('nomba-mismatch@example.com');
        $plan = $this->createPlan();
        $orderId = 'ca218976-bcbe-4f07-9c07-1a75468ed6d5';

        $this->fakeNomba($this->transaction($user, $plan, $orderId, 1000, true));

        $this->artisan('plans:confirm-nomba', ['order' => $orderId])
            ->assertSuccessful();

        $this->assertSame($plan->id, $user->fresh()->currentPlan()?->id);
        $this->assertDatabaseHas('user_plans', [
            'user_id' => $user->id,
            'provider_subscription_id' => $orderId,
            'status' => 'active',
            'amount' => 1000,
        ]);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function fakeNomba(array $transaction): void
    {
        Http::fake([
            'https://sandbox.nomba.com/v1/auth/token/issue' => Http::response([
                'code' => '00',
                'data' => ['access_token' => 'nomba-access-token'],
            ], 200),
            'https://sandbox.nomba.com/v1/checkout/transaction*' => Http::response([
                'code' => '00',
                'data' => $transaction,
            ], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(User $user, Plan $plan, string $orderId, float $amount, bool $paid): array
    {
        return [
            'success' => $paid,
            'order' => [
                'orderId' => $orderId,
                'orderReference' => $orderId,
                'customerEmail' => $user->email,
                'amount' => $amount,
                'currency' => 'NGN',
                'orderMetaData' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'type' => 'subscription',
                ],
            ],
            'transactionDetails' => [
                'status' => $paid ? 'PAYMENT SUCCESSFUL' : 'PENDING',
            ],
        ];
    }

    private function createMember(string $email): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 1,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    private function createPlan(): Plan
    {
        return Plan::create([
            'name' => 'Bronze',
            'slug' => 'bronze-'.Str::lower(Str::random(4)),
            'price' => 5000,
            'currency' => 'NGN',
            'duration' => 30,
            'status' => true,
        ]);
    }
}

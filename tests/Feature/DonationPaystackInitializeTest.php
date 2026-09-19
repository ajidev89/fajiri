<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\Donation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DonationPaystackInitializeTest extends TestCase
{
    use RefreshDatabase;

    protected Role $userRole;

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

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->userRole = Role::where('slug', 'user')->first();

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'access_abc123',
                    'reference' => 'PAY_testref',
                ],
            ], 200),
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => [
                    'link' => 'https://checkout.flutterwave.com/flw123',
                ],
            ], 200),
            '*' => Http::response([
                'conversion_rate' => 1500,
                'rates' => ['NGN' => 1500, 'USD' => 1],
            ], 200),
        ]);
    }

    public function test_guest_can_initialize_paystack_donation_for_ngn_campaign(): void
    {
        $campaign = $this->createCampaign('NGN');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/paystack/initialize", [
            'amount' => 5000,
            'email' => 'guest@example.com',
            'name' => 'Guest Donor',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc123');

        $this->assertDatabaseHas('donations', [
            'donatable_id' => $campaign->id,
            'email' => 'guest@example.com',
            'user_id' => null,
            'medium' => 'paystack',
            'status' => 'pending',
            'currency' => 'NGN',
        ]);
    }

    public function test_guest_can_initialize_paystack_donation_for_usd_campaign(): void
    {
        $campaign = $this->createCampaign('USD');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/paystack/initialize", [
            'amount' => 25,
            'email' => 'guest@example.com',
            'name' => 'Guest Donor',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc123')
            ->assertJsonMissing(['message' => 'Undefined property: stdClass::$id']);

        $donation = Donation::where('email', 'guest@example.com')->first();

        $this->assertNotNull($donation);
        $this->assertNull($donation->user_id);
        $this->assertSame('paystack', $donation->medium);
        $this->assertSame('USD', $donation->currency);
    }

    public function test_authenticated_user_without_profile_can_initialize_paystack_donation(): void
    {
        $campaign = $this->createCampaign('USD');
        $user = User::create([
            'email' => 'noprofile@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson("/v1/donations/campaign/{$campaign->id}/paystack/initialize", [
                'amount' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('donations', [
            'donatable_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'medium' => 'paystack',
            'status' => 'pending',
        ]);
    }

    public function test_guest_initialize_requires_email(): void
    {
        $campaign = $this->createCampaign('NGN');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/paystack/initialize", [
            'amount' => 5000,
        ])->assertStatus(422);
    }

    public function test_guest_can_initialize_without_a_name(): void
    {
        $campaign = $this->createCampaign('NGN');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/initialize", [
            'amount' => 5000,
            'email' => 'guest@example.com',
            'gateway' => 'paystack',
        ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('donations', [
            'donatable_id' => $campaign->id,
            'email' => 'guest@example.com',
            'name' => 'guest@example.com',
            'user_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_guest_can_initialize_donation_by_passing_gateway(): void
    {
        $campaign = $this->createCampaign('NGN');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/initialize", [
            'amount' => 5000,
            'email' => 'guest@example.com',
            'name' => 'Guest Donor',
            'gateway' => 'paystack',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc123');

        $this->assertDatabaseHas('donations', [
            'donatable_id' => $campaign->id,
            'email' => 'guest@example.com',
            'user_id' => null,
            'medium' => 'paystack',
            'status' => 'pending',
        ]);
    }

    public function test_guest_donation_is_tied_to_user_when_email_matches(): void
    {
        $campaign = $this->createCampaign('NGN');
        $member = $this->createMember('member@example.com');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/initialize", [
            'amount' => 2500,
            'email' => 'Member@example.com',
            'name' => 'Guest Name',
            'gateway' => 'flutterwave',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.authorization_url', 'https://checkout.flutterwave.com/flw123');

        $this->assertDatabaseHas('donations', [
            'donatable_id' => $campaign->id,
            'email' => 'Member@example.com',
            'user_id' => $member->id,
            'medium' => 'flutterwave',
            'status' => 'pending',
        ]);
    }

    public function test_authenticated_donor_is_not_replaced_by_another_users_email(): void
    {
        $campaign = $this->createCampaign('NGN');
        $donor = $this->createMember('donor@example.com');
        $other = $this->createMember('other@example.com');

        $this->actingAs($donor)
            ->postJson("/v1/donations/campaign/{$campaign->id}/initialize", [
                'amount' => 1000,
                'email' => $other->email,
                'gateway' => 'paystack',
            ])
            ->assertOk();

        $this->assertDatabaseHas('donations', [
            'donatable_id' => $campaign->id,
            'user_id' => $donor->id,
            'email' => $donor->email,
            'medium' => 'paystack',
        ]);
    }

    public function test_unknown_gateway_is_rejected(): void
    {
        $campaign = $this->createCampaign('NGN');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/initialize", [
            'amount' => 5000,
            'email' => 'guest@example.com',
            'name' => 'Guest Donor',
            'gateway' => 'wallet',
        ])->assertStatus(422);
    }

    protected function createMember(string $email): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    protected function createCampaign(string $currency): Campaign
    {
        $owner = User::create([
            'email' => 'owner-'.uniqid().'@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        return Campaign::create([
            'added_by' => $owner->id,
            'title' => 'Relief Campaign',
            'body' => 'Support families in need',
            'goal_amount' => 100000,
            'currency' => $currency,
            'status' => 'active',
            'type' => 'medical-aid',
        ]);
    }
}

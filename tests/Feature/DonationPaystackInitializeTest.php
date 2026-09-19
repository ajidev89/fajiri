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

    public function test_guest_initialize_requires_email_and_name(): void
    {
        $campaign = $this->createCampaign('NGN');

        $this->postJson("/v1/donations/campaign/{$campaign->id}/paystack/initialize", [
            'amount' => 5000,
        ])->assertStatus(422);
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

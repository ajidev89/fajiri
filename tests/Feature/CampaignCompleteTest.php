<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CampaignCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
        $this->seed(AddAdminAccount::class);

        $this->admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))->first();
    }

    public function test_admin_can_complete_a_campaign(): void
    {
        $campaign = $this->createCampaign(['status' => 'active', 'is_urgent' => true]);

        $this->actingAs($this->admin)
            ->postJson("/v1/campaigns/{$campaign->id}/complete")
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.is_urgent', false);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => 'completed',
            'is_urgent' => false,
        ]);
    }

    public function test_guest_cannot_complete_a_campaign(): void
    {
        $campaign = $this->createCampaign();

        $this->postJson("/v1/campaigns/{$campaign->id}/complete")
            ->assertUnauthorized();
    }

    public function test_completed_campaign_cannot_be_paid_for(): void
    {
        $campaign = $this->createCampaign(['status' => 'completed']);

        $this->postJson("/v1/donations/campaign/{$campaign->id}/initialize", [
            'amount' => 5000,
            'email' => 'guest@example.com',
            'name' => 'Guest Donor',
            'gateway' => 'paystack',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'This campaign is no longer accepting donations.');

        $this->assertDatabaseMissing('donations', [
            'donatable_id' => $campaign->id,
        ]);
    }

    public function test_completed_campaign_cannot_be_paid_for_via_wallet(): void
    {
        $campaign = $this->createCampaign(['status' => 'completed']);
        $donor = $this->createMember();

        $this->actingAs($donor)
            ->postJson("/v1/donations/campaign/{$campaign->id}/wallet", [
                'amount' => 1000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This campaign is no longer accepting donations.');

        $this->assertDatabaseMissing('donations', [
            'donatable_id' => $campaign->id,
        ]);
    }

    public function test_status_all_returns_only_active_and_completed_campaigns(): void
    {
        $this->createCampaign(['title' => 'Active Drive', 'status' => 'active']);
        $this->createCampaign(['title' => 'Finished Drive', 'status' => 'completed']);
        $this->createCampaign(['title' => 'Pending Drive', 'status' => 'pending']);
        $this->createCampaign(['title' => 'Rejected Drive', 'status' => 'rejected']);

        $response = $this->getJson('/v1/campaigns?status=all')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Active Drive', $titles);
        $this->assertContains('Finished Drive', $titles);
        $this->assertNotContains('Pending Drive', $titles);
        $this->assertNotContains('Rejected Drive', $titles);
    }

    protected function createMember(): User
    {
        $role = Role::where('slug', 'user')->first();

        return User::create([
            'email' => 'donor-'.uniqid().'@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    protected function createCampaign(array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'added_by' => $this->admin->id,
            'title' => 'Relief Campaign',
            'body' => 'Support families in need',
            'goal_amount' => 100000,
            'currency' => 'NGN',
            'status' => 'active',
            'type' => 'other',
        ], $overrides));
    }
}

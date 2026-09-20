<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignUrgentTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Flood Relief',
            'body' => 'Helping families affected by flooding.',
            'currency' => 'NGN',
            'goal_amount' => 500000,
            'days' => 30,
            'status' => 'active',
            'campaign_type' => 'organization',
            'type' => 'other',
        ], $overrides);
    }

    public function test_admin_can_create_an_urgent_campaign(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/v1/campaigns', $this->payload(['is_urgent' => true]))
            ->assertSuccessful()
            ->assertJsonPath('data.is_urgent', true);

        $this->assertDatabaseHas('campaigns', [
            'title' => 'Flood Relief',
            'is_urgent' => true,
        ]);
    }

    public function test_campaigns_default_to_not_urgent(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/v1/campaigns', $this->payload())
            ->assertSuccessful()
            ->assertJsonPath('data.is_urgent', false);

        $this->assertDatabaseHas('campaigns', [
            'title' => 'Flood Relief',
            'is_urgent' => false,
        ]);
    }

    public function test_admin_can_toggle_campaign_urgency(): void
    {
        $create = $this->actingAs($this->admin)
            ->postJson('/v1/campaigns', $this->payload(['is_urgent' => true]))
            ->assertSuccessful();

        $campaignId = $create->json('data.id');

        $this->actingAs($this->admin)
            ->putJson("/v1/campaigns/{$campaignId}", $this->payload([
                'title' => 'Flood Relief',
                'is_urgent' => false,
            ]))
            ->assertSuccessful()
            ->assertJsonPath('data.is_urgent', false);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'is_urgent' => false,
        ]);
    }

    public function test_urgent_endpoint_returns_only_active_urgent_campaigns(): void
    {
        Campaign::create([
            'added_by' => $this->admin->id,
            'title' => 'Urgent Active',
            'body' => 'Needs help now',
            'goal_amount' => 10000,
            'currency' => 'NGN',
            'status' => 'active',
            'type' => 'other',
            'is_urgent' => true,
        ]);

        Campaign::create([
            'added_by' => $this->admin->id,
            'title' => 'Urgent Pending',
            'body' => 'Not live yet',
            'goal_amount' => 10000,
            'currency' => 'NGN',
            'status' => 'pending',
            'type' => 'other',
            'is_urgent' => true,
        ]);

        Campaign::create([
            'added_by' => $this->admin->id,
            'title' => 'Ending Soon But Not Flagged',
            'body' => 'Closes shortly',
            'goal_amount' => 10000,
            'currency' => 'NGN',
            'status' => 'active',
            'type' => 'other',
            'is_urgent' => false,
            'end_date' => now()->addDays(3),
        ]);

        $this->getJson('/v1/campaigns/urgent')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Urgent Active')
            ->assertJsonPath('data.0.is_urgent', true);
    }

    public function test_campaigns_can_be_filtered_by_urgency(): void
    {
        Campaign::create([
            'added_by' => $this->admin->id,
            'title' => 'Urgent Drive',
            'body' => 'Needs help now',
            'goal_amount' => 10000,
            'currency' => 'NGN',
            'status' => 'active',
            'type' => 'other',
            'is_urgent' => true,
        ]);

        Campaign::create([
            'added_by' => $this->admin->id,
            'title' => 'Regular Drive',
            'body' => 'Normal campaign',
            'goal_amount' => 10000,
            'currency' => 'NGN',
            'status' => 'active',
            'type' => 'other',
            'is_urgent' => false,
        ]);

        $this->getJson('/v1/campaigns?is_urgent=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Urgent Drive');
    }
}

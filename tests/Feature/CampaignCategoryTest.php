<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCategoryTest extends TestCase
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
        ], $overrides);
    }

    public function test_admin_can_create_campaign_with_a_category(): void
    {
        $category = Category::create(['name' => 'Education', 'slug' => 'education']);

        $this->actingAs($this->admin)
            ->postJson('/v1/campaigns', $this->payload(['category_id' => $category->id]))
            ->assertSuccessful()
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.name', 'Education')
            // slug matches a legacy type, so `type` follows it
            ->assertJsonPath('data.type', 'education');
    }

    public function test_type_falls_back_to_other_for_custom_categories(): void
    {
        $category = Category::create(['name' => 'Orphan Support', 'slug' => 'orphan-support']);

        $this->actingAs($this->admin)
            ->postJson('/v1/campaigns', $this->payload(['category_id' => $category->id]))
            ->assertSuccessful()
            ->assertJsonPath('data.type', 'other');

        $this->assertDatabaseHas('campaigns', ['category_id' => $category->id, 'type' => 'other']);
    }

    public function test_unknown_category_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/v1/campaigns', $this->payload(['category_id' => 999999]))
            ->assertStatus(422);
    }

    public function test_campaigns_can_be_filtered_by_category(): void
    {
        $education = Category::create(['name' => 'Education', 'slug' => 'education']);
        $health = Category::create(['name' => 'Health', 'slug' => 'health']);

        $this->actingAs($this->admin)->postJson('/v1/campaigns', $this->payload(['title' => 'School Fees', 'category_id' => $education->id]));
        $this->actingAs($this->admin)->postJson('/v1/campaigns', $this->payload(['title' => 'Clinic', 'category_id' => $health->id]));

        $this->getJson('/v1/campaigns?category_id='.$education->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'School Fees');
    }
}

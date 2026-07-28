<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a dummy country with ID 1 to satisfy seeders
        Country::create([
            'id' => 1,
            'name' => 'Nigeria',
            'iso3' => 'NGA',
            'iso2' => 'NG',
            'currency' => 'NGN',
            'phone_code' => '+234',
        ]);

        // Run other seeders
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\AddAdminAccount::class);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_admin_endpoints()
    {
        $response = $this->getJson('/v1/admin/roles');
        $response->assertStatus(401);
    }

    /** @test */
    public function super_admin_can_access_all_admin_endpoints()
    {
        $superAdmin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        // Access role management
        $response = $this->actingAs($superAdmin)->getJson('/v1/admin/roles');
        $response->assertStatus(200);

        // Access users list
        $response = $this->actingAs($superAdmin)->getJson('/v1/users');
        $response->assertStatus(200);
    }

    /** @test */
    public function campaign_manager_can_only_access_campaign_related_endpoints()
    {
        $campaignManagerRole = Role::where('slug', 'campaign-manager')->first();
        $country = Country::first();

        $campaignManager = User::create([
            'email' => 'campaign@fajiri.org',
            'password' => bcrypt('password'),
            'role_id' => $campaignManagerRole->id,
            'country_id' => $country->id,
            'account_type' => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        // Access campaign analytics (allowed)
        $response = $this->actingAs($campaignManager)->getJson('/v1/campaigns/analytics');
        $response->assertStatus(200);

        // Access user management (forbidden)
        $response = $this->actingAs($campaignManager)->getJson('/v1/users');
        $response->assertStatus(403);

        // Access role management (forbidden)
        $response = $this->actingAs($campaignManager)->getJson('/v1/admin/roles');
        $response->assertStatus(403);
    }

    /** @test */
    public function role_management_endpoints_crud_operations()
    {
        $superAdmin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        // 1. Create Role
        $response = $this->actingAs($superAdmin)->postJson('/v1/admin/roles', [
            'name' => 'Custom Moderator',
            'permissions' => ['user_management', 'campaign_management']
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Custom Moderator');
        
        $roleId = $response->json('data.id');

        // 2. Read Role
        $response = $this->actingAs($superAdmin)->getJson("/v1/admin/roles/{$roleId}");
        $response->assertStatus(200);

        // 3. Update Role
        $response = $this->actingAs($superAdmin)->putJson("/v1/admin/roles/{$roleId}", [
            'name' => 'Updated Moderator',
            'permissions' => ['campaign_management']
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Updated Moderator');

        // 4. Delete Role
        $response = $this->actingAs($superAdmin)->deleteJson("/v1/admin/roles/{$roleId}");
        $response->assertStatus(200);
    }

    /** @test */
    public function admin_user_management_endpoints_crud_operations()
    {
        $superAdmin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        $donationManagerRole = Role::where('slug', 'donation-manager')->first();
        $country = Country::first();

        // 1. Store Admin User
        $response = $this->actingAs($superAdmin)->postJson('/v1/admin/users', [
            'email' => 'newadmin@fajiri.org',
            'password' => 'securePassword123',
            'role_id' => $donationManagerRole->id,
            'country_id' => $country->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.email', 'newadmin@fajiri.org');

        $adminUserId = $response->json('data.id');

        // 2. List Admins
        $response = $this->actingAs($superAdmin)->getJson('/v1/admin/users');
        $response->assertStatus(200);

        // 3. Update Admin User
        $campaignManagerRole = Role::where('slug', 'campaign-manager')->first();
        $response = $this->actingAs($superAdmin)->putJson("/v1/admin/users/{$adminUserId}", [
            'role_id' => $campaignManagerRole->id,
            'status' => 'suspended'
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'suspended');

        // 4. Delete Admin User
        $response = $this->actingAs($superAdmin)->deleteJson("/v1/admin/users/{$adminUserId}");
        $response->assertStatus(200);
    }

    /** @test */
    public function cannot_delete_protected_system_roles()
    {
        $superAdmin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        $role = Role::where('slug', 'super-admin')->first();

        $response = $this->actingAs($superAdmin)->deleteJson("/v1/admin/roles/{$role->id}");
        $response->assertStatus(403);
    }
}

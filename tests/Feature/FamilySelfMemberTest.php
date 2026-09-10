<?php

namespace Tests\Feature;

use App\Enums\Family\Relationship;
use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\FamilyMember;
use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\GenerateSelfFamilyMemberForUsers;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FamilySelfMemberTest extends TestCase
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
    }

    public function test_creating_a_user_creates_a_me_family_member(): void
    {
        $user = $this->createMember('owner@example.com', 'Ada', 'Obi');

        $this->assertDatabaseHas('family_members', [
            'user_id' => $user->id,
            'relationship' => Relationship::ME->value,
            'full_name' => 'Ada Obi',
            'parent_id' => null,
        ]);

        $this->actingAs($user)
            ->getJson('/v1/family-tree')
            ->assertOk()
            ->assertJsonFragment([
                'relationship' => Relationship::ME->value,
                'full_name' => 'Ada Obi',
            ]);
    }

    public function test_new_family_members_attach_to_the_me_record(): void
    {
        $user = $this->createMember('parent@example.com', 'Ada', 'Obi');
        $me = $user->selfFamilyMember;

        $this->actingAs($user)
            ->postJson('/v1/family-tree', [
                'full_name' => 'Kemi Obi',
                'dob' => '2018-05-01',
                'gender' => 'female',
                'relationship' => Relationship::DAUGHTER->value,
                'is_alive' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.relationship', Relationship::DAUGHTER->value)
            ->assertJsonPath('data.parent_id', $me->id);
    }

    public function test_cannot_create_or_delete_the_me_family_member(): void
    {
        $user = $this->createMember('protected@example.com', 'Ada', 'Obi');
        $me = $user->selfFamilyMember;

        $this->actingAs($user)
            ->postJson('/v1/family-tree', [
                'full_name' => 'Ada Obi',
                'dob' => '1992-01-01',
                'gender' => 'female',
                'relationship' => Relationship::ME->value,
                'is_alive' => true,
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson('/v1/family-tree/'.$me->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('family_members', [
            'id' => $me->id,
            'relationship' => Relationship::ME->value,
        ]);
    }

    public function test_seeder_backfills_me_records_for_existing_accounts(): void
    {
        $user = User::withoutEvents(fn () => User::create([
            'email' => 'legacy@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]));

        Profile::withoutEvents(fn () => $user->profile()->create([
            'first_name' => 'Legacy',
            'last_name' => 'User',
            'gender' => 'male',
            'dob' => '1988-03-12',
        ]));

        $this->assertFalse(
            FamilyMember::where('user_id', $user->id)->where('relationship', Relationship::ME->value)->exists()
        );

        $this->seed(GenerateSelfFamilyMemberForUsers::class);

        $this->assertDatabaseHas('family_members', [
            'user_id' => $user->id,
            'relationship' => Relationship::ME->value,
            'full_name' => 'Legacy User',
        ]);
    }

    protected function createMember(string $email, string $firstName, string $lastName): User
    {
        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $user->profile()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'dob' => '1992-01-01',
        ]);

        return $user->fresh(['profile', 'selfFamilyMember']);
    }
}

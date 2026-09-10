<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsersSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Country $nigeria;

    protected Country $ghana;

    protected Role $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nigeria = Country::create([
            'id' => 1,
            'name' => 'Nigeria',
            'iso3' => 'NGA',
            'iso2' => 'NG',
            'currency' => 'NGN',
            'phone_code' => '+234',
        ]);

        $this->ghana = Country::create([
            'name' => 'Ghana',
            'iso3' => 'GHA',
            'iso2' => 'GH',
            'currency' => 'GHS',
            'phone_code' => '+233',
        ]);

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AddAdminAccount::class);

        $this->admin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        $this->userRole = Role::where('slug', 'user')->first();
    }

    public function test_search_matches_email_phone_member_id_and_name(): void
    {
        $matching = $this->createMember([
            'email' => 'ada.obi@example.com',
            'phone' => '+2348011111111',
            'member_id' => 'FIM111111',
            'first_name' => 'Ada',
            'last_name' => 'Obi',
        ]);

        $this->createMember([
            'email' => 'chidi.okeke@example.com',
            'phone' => '+2348022222222',
            'member_id' => 'FIM222222',
            'first_name' => 'Chidi',
            'last_name' => 'Okeke',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/v1/users?search=ada.obi@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);

        $this->actingAs($this->admin)
            ->getJson('/v1/users?search=8011111111')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);

        $this->actingAs($this->admin)
            ->getJson('/v1/users?q=FIM111111')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);

        $this->actingAs($this->admin)
            ->getJson('/v1/users?search=Ada Obi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_status_filter_returns_suspended_users(): void
    {
        $active = $this->createMember([
            'email' => 'active.user@example.com',
            'status' => Status::ACTIVE->value,
            'first_name' => 'Active',
            'last_name' => 'User',
        ]);

        $suspended = $this->createMember([
            'email' => 'suspended.user@example.com',
            'status' => Status::SUSPENDED->value,
            'first_name' => 'Suspended',
            'last_name' => 'User',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/v1/users?status=suspended');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($suspended->id));
        $this->assertFalse($ids->contains($active->id));
    }

    public function test_account_type_and_country_filters(): void
    {
        $identified = $this->createMember([
            'email' => 'identified@example.com',
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'country_id' => $this->nigeria->id,
            'first_name' => 'Identified',
            'last_name' => 'Member',
        ]);

        $corporate = $this->createMember([
            'email' => 'corporate@example.com',
            'account_type' => AccountType::CORPORATE_MEMBERSHIP,
            'country_id' => $this->ghana->id,
            'first_name' => 'Corporate',
            'last_name' => 'Partner',
        ]);

        $accountTypeResponse = $this->actingAs($this->admin)
            ->getJson('/v1/users?account_type='.AccountType::CORPORATE_MEMBERSHIP->value);

        $accountTypeResponse->assertOk();
        $accountTypeIds = collect($accountTypeResponse->json('data'))->pluck('id');
        $this->assertTrue($accountTypeIds->contains($corporate->id));
        $this->assertFalse($accountTypeIds->contains($identified->id));

        $countryResponse = $this->actingAs($this->admin)
            ->getJson('/v1/users?country_id='.$this->ghana->id);

        $countryResponse->assertOk();
        $countryIds = collect($countryResponse->json('data'))->pluck('id');
        $this->assertTrue($countryIds->contains($corporate->id));
        $this->assertFalse($countryIds->contains($identified->id));
    }

    public function test_search_and_filter_can_be_combined(): void
    {
        $match = $this->createMember([
            'email' => 'amina.bello@example.com',
            'status' => Status::SUSPENDED->value,
            'account_type' => AccountType::PROGRAM_MEMBERSHIP,
            'first_name' => 'Amina',
            'last_name' => 'Bello',
        ]);

        $this->createMember([
            'email' => 'amina.active@example.com',
            'status' => Status::ACTIVE->value,
            'account_type' => AccountType::PROGRAM_MEMBERSHIP,
            'first_name' => 'Amina',
            'last_name' => 'Active',
        ]);

        $this->createMember([
            'email' => 'other.bello@example.com',
            'status' => Status::SUSPENDED->value,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'first_name' => 'Other',
            'last_name' => 'Bello',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/v1/users?search=Amina&status=suspended&account_type='.AccountType::PROGRAM_MEMBERSHIP->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_includes_non_active_users_when_no_status_filter_is_sent(): void
    {
        $suspended = $this->createMember([
            'email' => 'hidden.suspended@example.com',
            'status' => Status::SUSPENDED->value,
            'first_name' => 'Hidden',
            'last_name' => 'Suspended',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/v1/users');

        $response->assertOk();
        $this->assertTrue(collect($response->json('data'))->pluck('id')->contains($suspended->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMember(array $overrides): User
    {
        $user = User::create([
            'email' => $overrides['email'],
            'phone' => $overrides['phone'] ?? null,
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => $overrides['country_id'] ?? $this->nigeria->id,
            'account_type' => $overrides['account_type'] ?? AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => $overrides['status'] ?? Status::ACTIVE->value,
            'member_id' => $overrides['member_id'] ?? null,
        ]);

        $user->profile()->create([
            'first_name' => $overrides['first_name'],
            'last_name' => $overrides['last_name'],
            'gender' => 'male',
            'dob' => '1992-01-01',
        ]);

        return $user->fresh();
    }
}

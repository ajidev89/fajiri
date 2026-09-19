<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Otp;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthGenerateTokenTest extends TestCase
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

    public function test_default_otp_works_for_test_email_regardless_of_case(): void
    {
        $user = $this->createMember('Wisdomzilla13@gmail.com');

        $this->postJson('/v1/auth/generate-token', [
            'channel' => 'email',
            'identifier' => 'Wisdomzilla13@gmail.com',
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.type', 'bearer')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotEmpty($user->fresh()->tokens);
    }

    public function test_default_otp_matches_test_email_case_insensitively(): void
    {
        $this->createMember('wisdomzilla13@gmail.com');

        $this->postJson('/v1/auth/generate-token', [
            'channel' => 'email',
            'identifier' => 'Wisdomzilla13@gmail.com',
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'bearer');
    }

    public function test_regular_user_cannot_use_default_otp_without_a_sent_code(): void
    {
        $this->createMember('member@example.com');

        $this->postJson('/v1/auth/generate-token', [
            'channel' => 'email',
            'identifier' => 'member@example.com',
            'code' => '123456',
        ])->assertStatus(422);
    }

    public function test_regular_user_can_verify_their_hashed_otp(): void
    {
        $user = $this->createMember('member@example.com');

        Otp::create([
            'identifier' => $user->email,
            'channel' => 'email',
            'hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/v1/auth/generate-token', [
            'channel' => 'email',
            'identifier' => $user->email,
            'code' => '654321',
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'bearer');
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
}

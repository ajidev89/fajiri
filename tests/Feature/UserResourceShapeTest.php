<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserResourceShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_payload_matches_the_flutter_user_model(): void
    {
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

        $role = Role::where('slug', 'user')->first();

        $user = User::create([
            'email' => 'ada@example.com',
            'phone' => '+2348000000000',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);

        $user->profile()->create([
            'first_name' => 'Ada',
            'last_name' => 'Member',
            'gender' => 'female',
            'dob' => '1992-01-01',
        ]);

        $user->wallet()->create([
            'currency' => 'NGN',
            'balance' => 1500,
        ]);

        $payload = $this->actingAs($user->fresh())
            ->getJson('/v1/user')
            ->assertOk()
            ->json('data');

        $this->assertIsString($payload['id']);
        $this->assertSame('ada@example.com', $payload['email']);
        $this->assertFalse($payload['is_subscribed']);
        $this->assertIsString($payload['email_verified_at']);
        $this->assertSame('+2348000000000', $payload['phone']);
        $this->assertIsString($payload['member_id']);
        $this->assertSame($payload['member_id'], $payload['referral_code']);
        $this->assertSame('Nigeria', $payload['country']);
        $this->assertSame(1, $payload['country_id']);
        $this->assertSame(1, $payload['country_data']['id']);
        $this->assertSame('Nigeria', $payload['country_data']['name']);
        $this->assertSame('NGN', $payload['preferred_currency_code']);
        $this->assertIsInt($payload['role']['id']);
        $this->assertSame('user', $payload['role']['slug']);
        $this->assertIsString($payload['role']['created_at']);
        $this->assertIsString($payload['role']['updated_at']);
        $this->assertSame('Ada', $payload['profile']['first_name']);
        $this->assertIsString($payload['wallet']['id']);
        $this->assertIsString($payload['wallet']['balance']);
        $this->assertSame('NGN', $payload['wallet']['currency']);
        $this->assertIsString($payload['wallet']['withdrawal_total']);
        $this->assertFalse($payload['wallet']['has_pin']);
        $this->assertSame('identified-membership', $payload['account_type']);
        $this->assertIsString($payload['created_at']);
        $this->assertIsString($payload['updated_at']);
        $this->assertNull($payload['deleted_at']);
        $this->assertSame(0, $payload['referral_count']);
    }
}

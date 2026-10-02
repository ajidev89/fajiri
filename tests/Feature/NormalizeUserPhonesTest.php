<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use App\Support\PhoneNumber;
use Database\Seeders\NormalizeUserPhonesSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NormalizeUserPhonesTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_phone_numbers_are_stored_with_the_country_code(): void
    {
        $this->assertSame('+2348031234567', PhoneNumber::withCountryCode('08031234567', '+234'));
        $this->assertSame('+2348031234567', PhoneNumber::withCountryCode('2348031234567', '+234'));
        $this->assertSame('+2348031234567', PhoneNumber::withCountryCode('+234 803 123 4567', '+234'));

        $user = $this->createMember('local@example.com', '08031111111');

        $this->assertSame('+2348031111111', $user->fresh()->phone);
    }

    public function test_seeder_prefixes_existing_phones_with_the_country_code(): void
    {
        $local = $this->createMemberWithoutFormatting('local-existing@example.com', '08032222222');
        $missingPlus = $this->createMemberWithoutFormatting('digits@example.com', '2348033333333');
        $already = $this->createMemberWithoutFormatting('ready@example.com', '+2348044444444');
        $duplicate = $this->createMemberWithoutFormatting('duplicate@example.com', '08044444444');

        $this->seed(NormalizeUserPhonesSeeder::class);

        $this->assertSame('+2348032222222', $local->fresh()->phone);
        $this->assertSame('+2348033333333', $missingPlus->fresh()->phone);
        $this->assertSame('+2348044444444', $already->fresh()->phone);
        $this->assertSame('08044444444', $duplicate->fresh()->phone);
    }

    private function createMember(string $email, string $phone): User
    {
        return User::create([
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password123'),
            'role_id' => Role::where('slug', 'user')->first()->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
        ]);
    }

    private function createMemberWithoutFormatting(string $email, string $phone): User
    {
        return User::withoutEvents(fn () => $this->createMember($email, $phone));
    }
}

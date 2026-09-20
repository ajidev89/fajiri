<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Donation;
use App\Models\Need;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NeedCollectedAmountTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

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

        $this->owner = User::create([
            'email' => 'need-owner@example.com',
            'phone' => '+2348000000123',
            'password' => Hash::make('password123'),
            'role_id' => Role::where('slug', 'user')->first()->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response([
                'conversion_rate' => 1400.0,
                'rates' => ['NGN' => 1400.0, 'USD' => 0.000714286],
            ], 200),
        ]);
    }

    public function test_collected_amount_sums_the_usd_base_of_completed_donations(): void
    {
        $need = $this->createNeed('USD');

        $this->createCompletedDonation($need, 5.15, 7204.9);
        $this->createCompletedDonation($need, 3.58, 5000.0);

        $this->getJson("/v1/needs/{$need->id}?currency=USD")
            ->assertOk()
            ->assertJsonPath('data.collected_amount', 8.73)
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_admin_sees_the_usd_base_rather_than_the_settled_naira_total(): void
    {
        $admin = User::whereHas('role', fn ($query) => $query->where('slug', 'super-admin'))->first();
        $need = $this->createNeed('USD');

        $this->createCompletedDonation($need, 5.15, 7204.9);
        $this->createCompletedDonation($need, 3.58, 5000.0);

        $this->actingAs($admin)
            ->getJson("/v1/needs/{$need->id}")
            ->assertOk()
            ->assertJsonPath('data.collected_amount', 8.73)
            ->assertJsonPath('data.base_collected_amount', 8.73)
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_collected_amount_is_converted_for_a_viewer_in_another_currency(): void
    {
        $need = $this->createNeed('USD');

        $this->createCompletedDonation($need, 5.15, 7204.9);
        $this->createCompletedDonation($need, 3.58, 5000.0);

        $this->getJson("/v1/needs/{$need->id}?currency=NGN")
            ->assertOk()
            ->assertJsonPath('data.collected_amount', 12222)
            ->assertJsonPath('data.base_collected_amount', 8.73)
            ->assertJsonPath('data.base_currency', 'USD');
    }

    public function test_listing_needs_uses_the_same_usd_base(): void
    {
        $need = $this->createNeed('USD');

        $this->createCompletedDonation($need, 5.15, 7204.9);
        $this->createCompletedDonation($need, 3.58, 5000.0);

        $this->getJson('/v1/needs?currency=USD')
            ->assertOk()
            ->assertJsonPath('data.0.collected_amount', 8.73);
    }

    public function test_pending_donations_are_excluded_from_collected_amount(): void
    {
        $need = $this->createNeed('USD');

        $this->createCompletedDonation($need, 5.15, 7204.9);

        Donation::create([
            'donatable_type' => Need::class,
            'donatable_id' => $need->id,
            'amount' => 5000.0,
            'converted_amount' => 5000.0,
            'base_amount_usd' => 3.58,
            'rate' => 1.0,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);

        $this->assertEquals(5.15, $need->fresh()->collected_amount);

        $this->getJson("/v1/needs/{$need->id}?currency=USD")
            ->assertOk()
            ->assertJsonPath('data.collected_amount', 5.15);
    }

    protected function createNeed(string $currency): Need
    {
        return Need::create([
            'added_by' => $this->owner->id,
            'name' => 'Adamu Ibrahim',
            'age' => 12,
            'location' => 'Kano',
            'currency' => $currency,
            'amount' => 4000.0,
            'description' => 'School fees support',
            'urgency' => 'high',
        ]);
    }

    protected function createCompletedDonation(Need $need, float $amountInUsd, float $settledInNaira): Donation
    {
        return Donation::create([
            'donatable_type' => Need::class,
            'donatable_id' => $need->id,
            'amount' => $settledInNaira,
            'converted_amount' => $settledInNaira,
            'base_amount_usd' => $amountInUsd,
            'rate' => 1.0,
            'currency' => 'NGN',
            'status' => 'completed',
        ]);
    }
}

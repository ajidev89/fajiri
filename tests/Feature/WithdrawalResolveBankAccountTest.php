<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WithdrawalResolveBankAccountTest extends TestCase
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

        Country::create([
            'id' => 2,
            'name' => 'United States',
            'iso3' => 'USA',
            'iso2' => 'US',
            'currency' => 'USD',
            'phone_code' => '+1',
        ]);

        Country::create([
            'id' => 3,
            'name' => 'Canada',
            'iso3' => 'CAN',
            'iso2' => 'CA',
            'currency' => 'CAD',
            'phone_code' => '+1',
        ]);

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_nigerian_account_still_resolves_through_paystack(): void
    {
        Http::fake([
            'https://api.paystack.co/bank/resolve*' => Http::response([
                'status' => true,
                'message' => 'Account number resolved',
                'data' => [
                    'account_number' => '0022728151',
                    'account_name' => 'JOHN DOE',
                ],
            ], 200),
        ]);

        $this->actingAs($this->user(1))
            ->getJson('/v1/withdrawals/resolve-bank-account?account_number=0022728151&bank_code=058')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.account_name', 'JOHN DOE');
    }

    public function test_us_account_starts_a_stripe_bank_link(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('createCustomer')->once()->andReturn('cus_test');
            $mock->shouldReceive('createFinancialConnectionsSession')->once()->with('cus_test')->andReturn([
                'provider' => 'stripe',
                'session_id' => 'fcsess_test',
                'client_secret' => 'fcsess_secret',
            ]);
        });

        $user = $this->user(2);

        $this->actingAs($user)
            ->getJson('/v1/withdrawals/resolve-bank-account')
            ->assertOk()
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.client_secret', 'fcsess_secret')
            ->assertJsonPath('data.session_id', 'fcsess_test');

        $this->assertSame('cus_test', $user->fresh()->stripe_customer_id);
    }

    public function test_us_account_returns_the_holder_name_after_stripe_links_the_bank(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('resolveFinancialConnectionsSession')
                ->once()
                ->with('fcsess_test', 'cus_existing')
                ->andReturn([
                    'account_name' => 'Jane Austen',
                    'bank_name' => 'StripeBank',
                    'last4' => '6789',
                    'provider' => 'stripe',
                    'financial_connections_account' => 'fca_test',
                ]);
        });

        $user = $this->user(2, ['stripe_customer_id' => 'cus_existing']);

        $this->actingAs($user)
            ->getJson('/v1/withdrawals/resolve-bank-account?session_id=fcsess_test')
            ->assertOk()
            ->assertJsonPath('data.account_name', 'Jane Austen')
            ->assertJsonPath('data.bank_name', 'StripeBank')
            ->assertJsonPath('data.provider', 'stripe');
    }

    public function test_canadian_account_is_verified_through_stripe(): void
    {
        $this->mock(StripeService::class, function ($mock): void {
            $mock->shouldReceive('verifyCanadianBankAccount')
                ->once()
                ->with('000123456789', '11000000')
                ->andReturn([
                    'account_name' => null,
                    'bank_name' => 'STRIPE TEST BANK',
                    'account_number' => '000123456789',
                    'last4' => '6789',
                    'routing_number' => '11000000',
                    'provider' => 'stripe',
                    'bank_account_token' => 'btok_test',
                ]);
        });

        $this->actingAs($this->user(3))
            ->getJson('/v1/withdrawals/resolve-bank-account?account_number=000123456789&bank_code=11000000')
            ->assertOk()
            ->assertJsonPath('data.bank_name', 'STRIPE TEST BANK')
            ->assertJsonPath('data.provider', 'stripe')
            ->assertJsonPath('data.account_name', null);
    }

    public function test_canadian_account_requires_account_and_routing_numbers(): void
    {
        $this->actingAs($this->user(3))
            ->getJson('/v1/withdrawals/resolve-bank-account')
            ->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function user(int $countryId, array $overrides = []): User
    {
        return User::create(array_merge([
            'email' => 'member'.$countryId.'@example.com',
            'password' => Hash::make('password123'),
            'role_id' => Role::where('slug', 'user')->value('id'),
            'country_id' => $countryId,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ], $overrides));
    }
}

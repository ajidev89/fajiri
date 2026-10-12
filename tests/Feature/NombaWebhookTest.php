<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NombaWebhookTest extends TestCase
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

        Role::create([
            'id' => 1,
            'name' => 'Member',
            'slug' => 'member',
        ]);
    }

    public function test_short_subscription_reference_activates_the_matching_plan(): void
    {
        $user = $this->createMember('nomba-sub@example.com');
        $plan = Plan::create([
            'name' => 'Bronze',
            'slug' => 'bronze-'.Str::lower(Str::random(4)),
            'price' => 5000,
            'currency' => 'NGN',
            'duration' => 30,
            'status' => true,
        ]);
        $reference = 'nsub_'.Str::lower((string) Str::ulid());

        $payload = [
            'data' => [
                'status' => 'SUCCESSFUL',
                'orderReference' => $reference,
                'customerEmail' => $user->email,
                'amount' => '5000.00',
                'currency' => 'NGN',
                'orderMetaData' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'type' => 'subscription',
                ],
            ],
        ];

        $this->postJson('/v1/webhooks/nomba', $payload)->assertOk();
        $this->postJson('/v1/webhooks/nomba', $payload)->assertOk();

        $this->assertSame($plan->id, $user->fresh()->currentPlan()?->id);
        $this->assertSame(1, $user->plans()->count());
        $this->assertDatabaseHas('user_plans', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'nomba',
            'provider_subscription_id' => $reference,
        ]);
    }

    public function test_short_wallet_reference_credits_the_wallet_once(): void
    {
        $user = $this->createMember('nomba-wallet@example.com');
        $user->wallet()->create(['balance' => 0, 'currency' => 'NGN']);
        $reference = 'nwf_'.Str::lower((string) Str::ulid());

        $payload = [
            'data' => [
                'status' => 'SUCCESSFUL',
                'orderReference' => $reference,
                'customerEmail' => $user->email,
                'amount' => 2500,
                'currency' => 'NGN',
                'orderMetaData' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding',
                ],
            ],
        ];

        $this->postJson('/v1/webhooks/nomba', $payload)->assertOk();
        $this->postJson('/v1/webhooks/nomba', $payload)->assertOk();

        $this->assertSame(2500.0, (float) $user->wallet()->first()->balance);
        $this->assertSame(1, $user->transactions()->where('reference', $reference)->count());
    }

    private function createMember(string $email): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => 1,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }
}

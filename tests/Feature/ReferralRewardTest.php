<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Http\Repository\PlanRepository;
use App\Http\Traits\PlanActivationTrait;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\ReferralRewardService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralRewardTest extends TestCase
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

    public function test_referral_reward_stays_pending_until_the_referred_user_has_a_plan(): void
    {
        $referrer = $this->createMember('referrer@example.com');
        $referrer->wallet()->create(['currency' => 'NGN', 'balance' => 0]);

        $referred = $this->createMember('referred@example.com', $referrer->id);
        $referred->wallet()->create(['currency' => 'NGN', 'balance' => 10000]);

        app(ReferralRewardService::class)->recordPending($referred);

        $this->assertSame(0.0, (float) $referrer->wallet()->first()->balance);
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $referrer->wallet()->first()->id,
            'description' => 'Referral bonus',
            'status' => 'pending',
            'amount' => 5000,
        ]);

        app(ReferralRewardService::class)->releaseIfEligible($referred->fresh());

        $this->assertSame(0.0, (float) $referrer->wallet()->first()->balance);
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $referrer->wallet()->first()->id,
            'status' => 'pending',
        ]);

        Mail::fake();
        $plan = $this->createPlan('Bronze', 5000);
        app(PlanRepository::class)->subscribeUser($referred, $plan->id);

        $this->assertSame(5000.0, (float) $referrer->wallet()->first()->balance);
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $referrer->wallet()->first()->id,
            'description' => 'Referral bonus',
            'status' => 'completed',
            'amount' => 5000,
        ]);
        $this->assertSame(1, $referrer->wallet()->first()->transactions()->where('description', 'Referral bonus')->count());
    }

    public function test_activating_a_plan_releases_a_pending_referral_reward_once(): void
    {
        $referrer = $this->createMember('referrer-pay@example.com');
        $referrer->wallet()->create(['currency' => 'NGN', 'balance' => 0]);

        $referred = $this->createMember('referred-pay@example.com', $referrer->id);
        app(ReferralRewardService::class)->recordPending($referred);

        $plan = $this->createPlan('Silver', 10000);
        $activator = new class()
        {
            use PlanActivationTrait;

            public function run(User $user, Plan $plan): void
            {
                $this->activateUserPlan($user, $plan, 'paystack', 'SUB_123');
            }
        };

        $activator->run($referred, $plan);
        $activator->run($referred->fresh(), $plan);

        $this->assertSame(5000.0, (float) $referrer->wallet()->first()->balance);
        $this->assertSame(1, $referrer->wallet()->first()->transactions()->where('description', 'Referral bonus')->count());
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $referrer->wallet()->first()->id,
            'status' => 'completed',
        ]);
    }

    private function createPlan(string $name, float $price): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => $price,
            'currency' => 'NGN',
            'duration' => 30,
            'status' => true,
        ]);
    }

    private function createMember(string $email, ?string $referredBy = null): User
    {
        return User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => Status::ACTIVE,
            'referred_by' => $referredBy,
        ]);
    }
}

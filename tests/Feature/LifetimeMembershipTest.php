<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Jobs\Paystack\PaystackJob;
use App\Models\Country;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentGateway;
use App\Services\PaystackService;
use App\Services\StripeService;
use App\Services\SubscriptionAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class LifetimeMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Country::create([
            'id' => 1,
            'name' => 'Nigeria',
            'iso3' => 'NGA',
            'iso2' => 'NG',
            'currency' => 'NGN',
            'phone_code' => '+234',
        ]);

        Role::create(['id' => 1, 'name' => 'Member', 'slug' => 'member']);
    }

    public function test_a_confirmed_payment_gives_a_lifetime_membership_that_never_renews(): void
    {
        $user = $this->createMember('lifetime@example.com');
        $plan = $this->createPlan(AccountType::IDENTIFIED_MEMBERSHIP);
        $reference = 'nsub_'.Str::lower((string) Str::ulid());

        $attempts = app(SubscriptionAttemptService::class);
        $attempts->record($user, $plan, 'nomba', $reference, 5000, 'NGN');
        $this->assertNotNull($attempts->confirm('nomba', $reference, 5000, 'NGN'));

        $row = DB::table('user_plans')->where('provider_subscription_id', $reference)->first();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->expires_at);
        $this->assertFalse((bool) $row->auto_renew);
    }

    public function test_each_membership_type_is_a_one_time_lifetime_plan(): void
    {
        foreach (AccountType::cases() as $type) {
            $plan = $this->createPlan($type);

            $this->getJson("/v1/plans/{$plan->id}")
                ->assertOk()
                ->assertJsonPath('data.billing_type', 'one_time')
                ->assertJsonPath('data.is_lifetime', true)
                ->assertJsonPath('data.duration', null);
        }
    }

    public function test_paystack_checkout_is_a_one_time_charge_without_a_plan_code(): void
    {
        $user = $this->createMember('paystack-once@example.com');
        $plan = $this->createPlan(AccountType::PROGRAM_MEMBERSHIP);

        $this->mock(PaystackService::class, function ($mock) {
            $mock->shouldReceive('initializeTransaction')
                ->once()
                ->withArgs(fn (array $data) => ! array_key_exists('plan', $data)
                    && $data['amount'] === 500000
                    && $data['metadata']['type'] === 'subscription')
                ->andReturn(['authorization_url' => 'https://checkout.paystack.com/x']);
        });

        $result = app(PaymentGateway::class)->initializeSubscription($user, $plan, ['gateway' => 'paystack']);

        $this->assertSame('https://checkout.paystack.com/x', $result['authorization_url']);
    }

    public function test_a_paystack_one_time_charge_activates_the_membership(): void
    {
        $user = $this->createMember('paystack-webhook@example.com');
        $plan = $this->createPlan(AccountType::CORPORATE_MEMBERSHIP);
        $reference = 'psub_'.Str::lower((string) Str::ulid());
        app(SubscriptionAttemptService::class)->record($user, $plan, 'paystack', $reference, 5000, 'NGN');

        (new PaystackJob([
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 500000,
                'currency' => 'NGN',
                'plan' => [],
                'metadata' => ['type' => 'subscription', 'user_id' => $user->id, 'plan_id' => $plan->id],
            ],
        ]))->handle(app(SubscriptionAttemptService::class));

        $this->assertSame($plan->id, $user->fresh()->currentPlan()?->id);
        $this->assertNull($user->fresh()->currentPlan()->pivot->expires_at);
    }

    public function test_stripe_checkout_is_a_one_time_payment(): void
    {
        $user = $this->createMember('stripe-once@example.com');
        $plan = $this->createPlan(AccountType::IDENTIFIED_MEMBERSHIP, 'USD', 50);

        $this->mock(StripeService::class, function ($mock) use ($plan) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn ($u, $p, $amount, $currency) => $p->id === $plan->id && $amount === 50.0 && $currency === 'USD')
                ->andReturn((object) ['id' => 'cs_test_once', 'url' => 'https://checkout.stripe.com/x']);
        });

        app(PaymentGateway::class)->initializeSubscription($user, $plan, ['gateway' => 'stripe']);

        $this->assertDatabaseHas('user_plans', [
            'provider' => 'stripe',
            'provider_subscription_id' => 'cs_test_once',
            'status' => 'pending',
            'currency' => 'USD',
            'auto_renew' => false,
        ]);
    }

    private function createPlan(AccountType $type, string $currency = 'NGN', float $price = 5000): Plan
    {
        return Plan::create([
            'name' => 'Plan '.Str::random(5),
            'slug' => 'plan-'.Str::lower(Str::random(6)),
            'account_type' => $type,
            'price' => $price,
            'currency' => $currency,
            'duration' => null,
            'status' => true,
        ]);
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

<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class PlanRepository implements PlanRepositoryInterface
{
    use AuthUserTrait;

    protected $paymentGateway;
    protected $paystackService;

    public function __construct(
        \App\Services\PaymentGateway $paymentGateway,
        \App\Services\PaystackService $paystackService
    ) {
        $this->paymentGateway = $paymentGateway;
        $this->paystackService = $paystackService;
    }
    public function all(array $filters = [])
    {
        $user = $this->user();
        $query = Plan::query();

        // Admin should see all plans by default, unless filtered
        if ($user && $user->role && $user->role->slug === 'admin') {
            // Keep query as is
        } else {
            $query->where('status', true);
        }

        if (isset($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        return $query->get();
    }

    public function findById($id)
    {
        return Plan::findOrFail($id);
    }

    public function store(array $data)
    {
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        $plan = Plan::create($data);
        
        $this->syncWithGateways($plan);

        return $plan;
    }
    public function syncWithGateways(Plan $plan)
    {
        try {
            
            // 1. Sync with Paystack (for NGN)
            if (!$plan->paystack_plan_code) {
                $paystackPlan = $this->paystackService->createPlan([
                    'name' => $plan->name,
                    'interval' => $this->mapDurationToInterval($plan->duration),
                    'amount' => $plan->price * 100, // Paystack amount is in kobo
                    'currency' => 'NGN'
                ]);
                
                if ($paystackPlan) {
                    $plan->paystack_plan_code = $paystackPlan['plan_code'];
                }
            }

            // 2. Sync with Stripe (for non-NGN)
            if (!$plan->stripe_price_id) {
                if (!$plan->stripe_product_id) {
                    // Ensure a name is always sent to Stripe. If the local plan name is missing, use a placeholder.
                    $productName = $plan->name ?: 'Plan ' . $plan->id;
                    $stripeProduct = $this->paymentGateway->getStripeService()->createProduct([
                        'name' => $productName,
                        'description' => $plan->description ?: '',
                        // Include metadata to aid debugging and linking back to our system
                        'metadata' => [
                            'local_plan_id' => $plan->id,
                            'provider' => 'stripe',
                        ],
                    ]);
                    $plan->stripe_product_id = $stripeProduct['id'] ?? null;
                }

                $stripePrice = $this->paymentGateway->getStripeService()->createPrice([
                    'product' => $plan->stripe_product_id,
                    'unit_amount' => $plan->price * 100,
                    'currency' => strtolower($plan->currency ?? 'USD'),
                    'recurring' => ['interval' => $this->mapDurationToStripeInterval($plan->duration)],
                    'metadata' => [
                        'local_plan_id' => $plan->id,
                        'provider' => 'stripe',
                    ],
                ]);

                if ($stripePrice) {
                    $plan->stripe_price_id = $stripePrice['id'];
                }
            }

            $plan->save();
        } catch (\Exception $e) {
            \Log::error('Gateway Sync Error: ' . $e->getMessage());
        }
    }

    protected function mapDurationToInterval($days)
    {
        if ($days >= 365) return 'annually';
        if ($days >= 30) return 'monthly';
        if ($days >= 7) return 'weekly';
        return 'daily';
    }

    protected function mapDurationToStripeInterval($days)
    {
        if ($days >= 365) return 'year';
        if ($days >= 30) return 'month';
        if ($days >= 7) return 'week';
        return 'day';
    }

    public function initializeSubscription($user, $planId, array $options = [])
    {
        $plan = $this->findById($planId);
        return $this->paymentGateway->initializeSubscription($user, $plan, $options);
    }

    public function update($id, array $data)
    {
        $plan = $this->findById($id);
        $plan->update($data);
        
        $this->syncWithGateways($plan);

        return $plan;
    }

    public function delete($id)
    {
        $plan = $this->findById($id);

        if ($plan->users()->exists()) {
            throw new \Exception("Cannot delete plan '{$plan->name}' because it has existing subscribers.");
        }

        return $plan->delete();
    }

    public function subscribeUser($user, $planId, $duration = null, $autoRenew = true)
    {
        return DB::transaction(function () use ($user, $planId, $duration, $autoRenew) {
            $plan = Plan::findOrFail($planId);
            
            // Handle Payment if plan is not free
            if ($plan->price > 0) {
                $wallet = $user->wallet()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
                
                if ($wallet->balance < $plan->price) {
                    throw new \Exception("Insufficient wallet balance to subscribe to this plan.");
                }

                $wallet->decrement('balance', $plan->price);

                // Create transaction record
                $wallet->transactions()->create([
                    'amount' => $plan->price,
                    'type' => 'withdrawal',
                    'description' => "Subscription to {$plan->name} plan",
                    'reference' => 'SUB_' . str($plan->name)->slug() . '_' . uniqid(),
                    'status' => 'completed',
                ]);
            }

            // Deactivate current active plans
            DB::table('user_plans')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            $startedAt = now();
            $expiresAt = $duration ? $startedAt->copy()->addDays($duration) : $startedAt->copy()->addDays($plan->duration);

            $user->plans()->attach($plan->id, [
                'id' => \Illuminate\Support\Str::uuid(),
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'auto_renew' => $autoRenew,
            ]);

            // Create notification
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'title' => 'Plan Subscribed',
                'message' => "You have successfully subscribed to the '{$plan->name}' plan.",
                'type' => 'plan_subscription',
                'data' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'amount' => $plan->price,
                    'currency' => $plan->currency
                ]
            ]);

            return $user->currentPlan();
        });
    }

    public function renewSubscription($userPlanId)
    {
        return DB::transaction(function () use ($userPlanId) {
            $userPlanPivot = DB::table('user_plans')->where('id', $userPlanId)->first();
            
            if (!$userPlanPivot) {
                throw new \Exception("User plan not found.");
            }

            $user = \App\Models\User::find($userPlanPivot->user_id);
            $plan = Plan::find($userPlanPivot->plan_id);

            // Handle Payment if plan is not free
            if ($plan->price > 0) {
                $wallet = $user->wallet()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
                
                if ($wallet->balance < $plan->price) {
                    throw new \Exception("Insufficient wallet balance to renew {$plan->name} plan.");
                }

                $wallet->decrement('balance', $plan->price);

                // Create transaction record
                $wallet->transactions()->create([
                    'amount' => $plan->price,
                    'type' => 'withdrawal',
                    'description' => "Renewal of {$plan->name} plan",
                    'reference' => 'RENEW_' . str($plan->name)->slug() . '_' . uniqid(),
                    'status' => 'completed',
                ]);
            }

            // Deactivate old plan
            DB::table('user_plans')->where('id', $userPlanId)->update(['status' => 'inactive']);

            // Create new plan record (renewal)
            $startedAt = now(); // Or use old expires_at if you want seamless renewal
            $expiresAt = $startedAt->copy()->addDays($plan->duration);

            $user->plans()->attach($plan->id, [
                'id' => \Illuminate\Support\Str::uuid(),
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'auto_renew' => true,
            ]);

            // Create notification
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'title' => 'Plan Renewed',
                'message' => "Your subscription to the '{$plan->name}' plan has been successfully renewed.",
                'type' => 'plan_renewal',
                'data' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'amount' => $plan->price,
                    'currency' => $plan->currency
                ]
            ]);

            return $user->currentPlan();
        });
    }
}

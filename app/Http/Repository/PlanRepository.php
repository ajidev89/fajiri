<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class PlanRepository implements PlanRepositoryInterface
{
    use AuthUserTrait;

    protected $revenueCatService;

    public function __construct(\App\Http\Services\RevenueCatService $revenueCatService)
    {
        $this->revenueCatService = $revenueCatService;
    }
    public function all()
    {
        $user = $this->user();

        // Admin should see all plans
        if ($user && $user->role && $user->role->slug === 'admin') {
            return Plan::all();
        }

        // Users see active plans in their currency
        $currency = ($user && $user->wallet) ? $user->wallet->currency : 'NGN';

        return Plan::where('status', true)->where('currency', $currency)->get();
    }

    public function findById($id)
    {
        return Plan::findOrFail($id);
    }

    public function store(array $data)
    {
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        $plan = Plan::create($data);

        // Sync with RevenueCat
        $this->syncWithRevenueCat($plan);

        return $plan;
    }

    protected function syncWithRevenueCat(Plan $plan)
    {
        try {
            $entitlementId = config('services.revenuecat.default_entitlement_id');
            $offeringId = config('services.revenuecat.default_offering_id');

            // 1. Ensure shared Entitlement exists
            $this->revenueCatService->ensureEntitlementExists($entitlementId);
            $plan->rc_entitlement_id = $entitlementId;

            // 2. Ensure shared Offering exists
            $this->revenueCatService->ensureOfferingExists($offeringId);
            $plan->rc_offering_id = $offeringId;

            // 3. Create unique Package for this Plan
            $package = $this->revenueCatService->createPackage($offeringId, $plan->slug, $plan->name);
            if ($package) {
                $plan->rc_package_id = $package['id'];
            }

            // 4. Link Store Products if provided
            // Note: This part requires the Product to be registered in RevenueCat first.
            // If the user provided rc_product_id_ios/android, we assume they are store identifiers.
            
            $plan->save();
        } catch (\Exception $e) {
            \Log::error('RevenueCat Sync Error: ' . $e->getMessage());
        }
    }

    public function update($id, array $data)
    {
        $plan = $this->findById($id);
        $plan->update($data);

        // Optional: Re-sync if critical fields changed
        $this->syncWithRevenueCat($plan);

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
            $user->plans()->updateExistingPivotAttributes(
                $user->plans()->wherePivot('status', 'active')->pluck('user_plans.id'), 
                ['status' => 'inactive']
            );

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

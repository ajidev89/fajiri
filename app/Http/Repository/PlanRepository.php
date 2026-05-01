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

        // Sync with RevenueCat
        $this->syncWithRevenueCat($plan);

        return $plan;
    }

    protected function syncWithRevenueCat(Plan $plan)
    {
        try {
            $entitlementId = config('services.revenuecat.default_entitlement_id');
            $offeringId = config('services.revenuecat.default_offering_id');
            $appIdIos = config('services.revenuecat.app_id_ios');
            $appIdAndroid = config('services.revenuecat.app_id_android');

            // 1. Ensure shared Entitlement & Offering exist
            $this->revenueCatService->ensureEntitlementExists($entitlementId);
            $this->revenueCatService->ensureOfferingExists($offeringId);
            
            $plan->rc_entitlement_id = $entitlementId;
            $plan->rc_offering_id = $offeringId;

            // 2. Create the Package (The Wrapper)
            $packageId = $this->revenueCatService->createPackage($offeringId, $plan->slug, $plan->name);
            if ($packageId) {
                $plan->rc_package_id = $packageId;
            }

            // 3. Register and Link iOS Product
            if ($plan->rc_product_id_ios && $appIdIos) {
                $rcProdId = $this->revenueCatService->registerProduct($plan->rc_product_id_ios, $plan->name . " (iOS)", $appIdIos, $plan->duration);
                if ($rcProdId) {
                    $this->revenueCatService->linkProductToEntitlement($entitlementId, $rcProdId);
                    $this->revenueCatService->attachProductToPackage($offeringId, $plan->slug, $rcProdId);
                }
            }

            // 4. Register and Link Android Product
            if ($plan->rc_product_id_android && $appIdAndroid) {
                $rcProdId = $this->revenueCatService->registerProduct($plan->rc_product_id_android, $plan->name . " (Android)", $appIdAndroid, $plan->duration);
                if ($rcProdId) {
                    $this->revenueCatService->linkProductToEntitlement($entitlementId, $rcProdId);
                    $this->revenueCatService->attachProductToPackage($offeringId, $plan->slug, $rcProdId);
                }
            }
            
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

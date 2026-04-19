<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class PlanRepository implements PlanRepositoryInterface
{
    use AuthUserTrait;
    public function all()
    {
        return Plan::where('status', true)->where('currency', $this->user()->wallet->currency)->get();
    }

    public function findById($id)
    {
        return Plan::findOrFail($id);
    }

    public function store(array $data)
    {
        $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        return Plan::create($data);
    }

    public function update($id, array $data)
    {
        $plan = $this->findById($id);
        $plan->update($data);
        return $plan;
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

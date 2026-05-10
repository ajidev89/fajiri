<?php

namespace App\Http\Traits;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait PlanActivationTrait
{
    protected function activateUserPlan(User $user, Plan $plan, $provider, $providerSubscriptionId)
    {
        DB::transaction(function () use ($user, $plan, $provider, $providerSubscriptionId) {
            // Deactivate current active plans
            DB::table('user_plans')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            $startedAt = now();
            $expiresAt = $startedAt->copy()->addDays($plan->duration);

            $user->plans()->attach($plan->id, [
                'id' => Str::uuid(),
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'auto_renew' => true,
                'provider' => $provider,
                'provider_subscription_id' => $providerSubscriptionId,
            ]);

            // Create notification
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'title' => 'Plan Activated',
                'message' => "Your '{$plan->name}' plan is now active.",
                'type' => 'plan_activation',
            ]);
        });
    }

    protected function deactivateUserPlanBySubscriptionId($provider, $subscriptionId)
    {
        DB::table('user_plans')
            ->where('provider', $provider)
            ->where('provider_subscription_id', $subscriptionId)
            ->update(['status' => 'inactive']);
    }
}

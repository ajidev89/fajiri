<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionAttemptService
{
    public function record(User $user, Plan $plan, string $provider, string $reference, float $amount, string $currency): void
    {
        DB::table('user_plans')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'auto_renew' => true,
            'provider' => $provider,
            'provider_subscription_id' => $reference,
            'amount' => $this->money($amount),
            'currency' => strtoupper($currency),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Confirm a stored attempt when the paid amount and currency match.
     *
     * @return array{user: User, plan: Plan, amount: float, currency: string}|null
     */
    public function confirm(string $provider, string $reference, mixed $paidAmount, ?string $paidCurrency, ?string $gatewaySubscriptionId = null): ?array
    {
        return DB::transaction(function () use ($provider, $reference, $paidAmount, $paidCurrency, $gatewaySubscriptionId) {
            $attempt = DB::table('user_plans')
                ->where('provider', $provider)
                ->where(function ($query) use ($reference, $gatewaySubscriptionId) {
                    $query->where('provider_subscription_id', $reference);

                    if ($gatewaySubscriptionId !== null && $gatewaySubscriptionId !== '') {
                        $query->orWhere('provider_subscription_id', $gatewaySubscriptionId);
                    }
                })
                ->lockForUpdate()
                ->first();

            if ($attempt === null) {
                Log::error('Subscription payment could not be matched to an attempt', [
                    'provider' => $provider,
                    'reference' => $reference,
                ]);

                return;
            }

            if ($attempt->status === 'active') {
                return;
            }

            if ($attempt->status !== 'pending') {
                return;
            }

            if (! $this->pricesMatch($attempt->amount, $attempt->currency, $paidAmount, $paidCurrency)) {
                DB::table('user_plans')->where('id', $attempt->id)->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);

                Log::error('Subscription payment price did not match the attempt', [
                    'provider' => $provider,
                    'reference' => $reference,
                    'expected_amount' => $attempt->amount,
                    'expected_currency' => $attempt->currency,
                    'paid_amount' => $paidAmount,
                    'paid_currency' => $paidCurrency,
                ]);

                return;
            }

            $user = User::query()->find($attempt->user_id);
            $plan = Plan::query()->find($attempt->plan_id);

            if (! $user || ! $plan) {
                return;
            }

            DB::table('user_plans')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive', 'updated_at' => now()]);

            $startedAt = now();

            DB::table('user_plans')->where('id', $attempt->id)->update([
                'status' => 'active',
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addDays($plan->duration),
                'provider_subscription_id' => $gatewaySubscriptionId ?: $attempt->provider_subscription_id,
                'updated_at' => now(),
            ]);

            Notification::create([
                'user_id' => $user->id,
                'title' => 'Plan Activated',
                'message' => "Your '{$plan->name}' plan is now active.",
                'type' => 'plan_activation',
            ]);

            app(ReferralRewardService::class)->releaseIfEligible($user);

            return [
                'user' => $user,
                'plan' => $plan,
                'amount' => (float) $attempt->amount,
                'currency' => (string) $attempt->currency,
            ];
        });
    }

    private function pricesMatch(mixed $expectedAmount, ?string $expectedCurrency, mixed $paidAmount, ?string $paidCurrency): bool
    {
        if ($expectedCurrency === null || $paidCurrency === null || $paidCurrency === '') {
            return false;
        }

        if (strtoupper($expectedCurrency) !== strtoupper($paidCurrency)) {
            return false;
        }

        return bccomp($this->money($expectedAmount), $this->money($paidAmount), 2) === 0;
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}

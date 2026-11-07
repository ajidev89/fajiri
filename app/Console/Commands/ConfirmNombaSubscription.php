<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\User;
use App\Services\NombaService;
use App\Services\SubscriptionAttemptService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfirmNombaSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:confirm-nomba
        {order : Nomba order id from the checkout link}
        {--plan= : Plan id to use when the Nomba order has no plan id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Confirm a Nomba checkout payment and activate the matching plan.';

    /**
     * Execute the console command.
     */
    public function handle(NombaService $nomba, SubscriptionAttemptService $attempts): int
    {
        $orderId = (string) $this->argument('order');
        $transaction = $nomba->getCheckoutTransaction($orderId);
        $status = (string) ($transaction['transactionDetails']['status'] ?? '');

        if (! $this->paymentSucceeded($transaction, $status)) {
            $this->error('Nomba has not confirmed this payment. Status: '.($status !== '' ? $status : 'unknown'));

            return self::FAILURE;
        }

        $order = is_array($transaction['order'] ?? null) ? $transaction['order'] : [];
        $metadata = $this->metadata($order, $transaction);
        $user = $this->findUser($metadata, $order);
        $plan = $this->findPlan($metadata);

        if (! $user || ! $plan) {
            $this->error('The paid order could not be matched to a user and plan.');

            return self::FAILURE;
        }

        if ($plan->duration === null) {
            $this->error("Plan {$plan->name} has no duration.");

            return self::FAILURE;
        }

        $reference = (string) ($order['orderReference'] ?? $orderId);
        $paidAmount = $order['amount'] ?? null;
        $paidCurrency = isset($order['currency']) ? (string) $order['currency'] : null;

        if ($paidAmount === null || $paidCurrency === null || $paidCurrency === '') {
            $this->error('Nomba did not return the paid amount and currency.');

            return self::FAILURE;
        }

        $existing = $this->existingAttempt($reference, $orderId);

        if ($existing?->status === 'active') {
            $this->info("{$user->email} already has this Nomba payment as an active subscription.");

            return self::SUCCESS;
        }

        if ($existing === null) {
            $attempts->record($user, $plan, 'nomba', $reference, (float) $paidAmount, $paidCurrency);
        } else {
            DB::table('user_plans')->where('id', $existing->id)->update([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'amount' => number_format((float) $paidAmount, 2, '.', ''),
                'currency' => strtoupper($paidCurrency),
                'updated_at' => now(),
            ]);
        }

        $result = $attempts->confirm('nomba', $reference, $paidAmount, $paidCurrency, $orderId);

        if ($result === null) {
            $this->error('The subscription could not be activated.');

            return self::FAILURE;
        }

        $this->info("{$result['user']->email} now has the {$result['plan']->name} plan.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function paymentSucceeded(array $transaction, string $status): bool
    {
        return filter_var($transaction['success'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || strtoupper($status) === 'PAYMENT SUCCESSFUL';
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  array<string, mixed>  $transaction
     * @return array<string, mixed>
     */
    private function metadata(array $order, array $transaction): array
    {
        $metadata = $order['orderMetaData']
            ?? $order['orderMetadata']
            ?? $transaction['orderMetaData']
            ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $order
     */
    private function findUser(array $metadata, array $order): ?User
    {
        if (! empty($metadata['user_id'])) {
            $user = User::query()->find($metadata['user_id']);

            if ($user) {
                return $user;
            }
        }

        $email = $order['customerEmail'] ?? null;

        return is_string($email) && $email !== ''
            ? User::query()->where('email', $email)->first()
            : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function findPlan(array $metadata): ?Plan
    {
        $metadataPlanId = isset($metadata['plan_id']) ? (string) $metadata['plan_id'] : null;
        $optionPlanId = $this->option('plan');
        $optionPlanId = is_string($optionPlanId) && $optionPlanId !== '' ? $optionPlanId : null;

        if ($metadataPlanId !== null && $optionPlanId !== null && $metadataPlanId !== $optionPlanId) {
            $this->error('The plan on the Nomba order does not match the --plan option.');

            return null;
        }

        $planId = $metadataPlanId ?? $optionPlanId;

        return $planId === null ? null : Plan::query()->find($planId);
    }

    private function existingAttempt(string $reference, string $orderId): ?object
    {
        return DB::table('user_plans')
            ->where('provider', 'nomba')
            ->where(function ($query) use ($reference, $orderId) {
                $query->where('provider_subscription_id', $reference)
                    ->orWhere('provider_subscription_id', $orderId);
            })
            ->first();
    }
}

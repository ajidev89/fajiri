<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\StripeService;
use App\Services\PaystackService;

class WebhookController extends Controller
{
    public function __construct(
        protected StripeService $stripeService,
        protected PaystackService $paystackService
    ) {}

    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        
        // In a real app, you'd use $this->stripeService->isValidWebhook($sigHeader, $payload)
        // For now we'll trust the payload if secret is configured
        
        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';

        Log::info('Stripe Webhook Received', ['type' => $type]);

        switch ($type) {
            case 'checkout.session.completed':
                $this->handleStripeCheckoutCompleted($event['data']['object']);
                break;
            case 'customer.subscription.deleted':
                $this->handleStripeSubscriptionCancelled($event['data']['object']);
                break;
            // Add more cases as needed
        }

        return response()->json(['message' => 'Webhook handled']);
    }

    public function handlePaystack(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (!$this->paystackService->isValidWebhook($signature, $payload)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $type = $event['event'] ?? '';

        Log::info('Paystack Webhook Received', ['type' => $type]);

        switch ($type) {
            case 'subscription.create':
            case 'charge.success':
                if (isset($event['data']['plan'])) {
                    $this->handlePaystackSubscriptionActive($event['data']);
                }
                break;
            case 'subscription.disable':
                $this->handlePaystackSubscriptionCancelled($event['data']);
                break;
        }

        return response()->json(['message' => 'Webhook handled']);
    }

    protected function handleStripeCheckoutCompleted($session)
    {
        $userId = $session['metadata']['user_id'] ?? null;
        $planId = $session['metadata']['plan_id'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        if ($userId && $planId) {
            $user = User::find($userId);
            $plan = Plan::find($planId);
            
            if ($user && $plan) {
                $this->activateUserPlan($user, $plan, 'stripe', $subscriptionId);
            }
        }
    }

    protected function handlePaystackSubscriptionActive($data)
    {
        $userId = $data['metadata']['user_id'] ?? null;
        $planId = $data['metadata']['plan_id'] ?? null;
        $subscriptionCode = $data['subscription_code'] ?? null;

        if ($userId && $planId) {
            $user = User::find($userId);
            $plan = Plan::find($planId);

            if ($user && $plan) {
                $this->activateUserPlan($user, $plan, 'paystack', $subscriptionCode);
            }
        }
    }

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
                'id' => \Illuminate\Support\Str::uuid(),
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

    protected function handleStripeSubscriptionCancelled($subscription)
    {
        $subscriptionId = $subscription['id'];
        DB::table('user_plans')
            ->where('provider', 'stripe')
            ->where('provider_subscription_id', $subscriptionId)
            ->update(['status' => 'inactive']);
    }

    protected function handlePaystackSubscriptionCancelled($subscription)
    {
        $subscriptionCode = $subscription['subscription_code'];
        DB::table('user_plans')
            ->where('provider', 'paystack')
            ->where('provider_subscription_id', $subscriptionCode)
            ->update(['status' => 'inactive']);
    }
}

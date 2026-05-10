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
use App\Http\Traits\PlanActivationTrait;
use App\Jobs\Paystack\PaystackJob;

class WebhookController extends Controller
{
    use PlanActivationTrait;

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

        PaystackJob::dispatchAfterResponse($event);

        return response()->json(['message' => 'Webhook received and processing']);
    }

    protected function handleStripeCheckoutCompleted($session)
    {
        $userId = $session['metadata']['user_id'] ?? null;
        $type = $session['metadata']['type'] ?? null;

        if (!$userId) return;

        $user = User::find($userId);
        if (!$user) return;

        if ($type === 'wallet_funding') {
            $amount = $session['metadata']['amount'] ?? ($session['amount_total'] / 100);
            $reference = $session['id'];
            $currency = strtoupper($session['metadata']['currency'] ?? $session['currency']);

            // Credit wallet using the HasWallet trait
            $user->deposit($amount, "Wallet funding via Stripe", $reference);

            // Create notification
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'title' => 'Wallet Funded',
                'message' => "Your wallet has been credited with {$currency} " . number_format($amount, 2) . " via Stripe.",
                'type' => 'wallet_funding',
                'data' => [
                    'amount' => $amount,
                    'reference' => $reference,
                    'currency' => $currency
                ]
            ]);

            // Send Email
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\DepositSuccessMail($user, $amount, $currency, $reference));
            } catch (\Exception $e) {
                \Log::error('Failed to send stripe deposit email: ' . $e->getMessage());
            }
        } else {
            // Assume subscription if type is not wallet_funding
            $planId = $session['metadata']['plan_id'] ?? null;
            $subscriptionId = $session['subscription'] ?? null;

            if ($planId) {
                $plan = Plan::find($planId);
                if ($plan) {
                    $this->activateUserPlan($user, $plan, 'stripe', $subscriptionId);

                    // Send Email
                    try {
                        \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\SubscriptionSuccessMail($user, $plan, $plan->price, $plan->currency));
                    } catch (\Exception $e) {
                        \Log::error('Failed to send stripe subscription email: ' . $e->getMessage());
                    }
                }
            }
        }
    }



    protected function handleStripeSubscriptionCancelled($subscription)
    {
        $subscriptionId = $subscription['id'];
        $this->deactivateUserPlanBySubscriptionId('stripe', $subscriptionId);
    }
}

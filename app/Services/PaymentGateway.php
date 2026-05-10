<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use Exception;

class PaymentGateway
{
    public function __construct(
        protected StripeService $stripeService,
        protected PaystackService $paystackService,
        protected CurrencyService $currencyService
    ) {}

    public function getCurrencyService()
    {
        return $this->currencyService;
    }

    /**
     * Get the appropriate service based on currency
     */
    public function getService(string $currency)
    {
        if (strtoupper($currency) === 'NGN') {
            return $this->paystackService;
        }

        return $this->stripeService;
    }

    public function getStripeService()
    {
        return $this->stripeService;
    }

    /**
     * Initialize a subscription
     */
    public function initializeSubscription(User $user, Plan $plan, array $options = [])
    {
        // Use user's country currency or request's detected currency to choose gateway
        $currency = $user->country->currency ?? request()->detected_currency ?? 'USD';
        
        if (strtoupper($currency) === 'NGN') {
            if (!$plan->paystack_plan_code) {
                throw new Exception("Paystack plan code not set for this plan.");
            }

            return $this->paystackService->initializeSubscription([
                'email' => $user->email,
                'amount' => $plan->price * 100,
                'plan' => $plan->paystack_plan_code,
                'callback_url' => $options['success_url'] ?? config('app.url') . '/payments/verify/paystack',
                'metadata' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'type' => 'subscription'
                ]
            ]);
        } else {
            // Any currency other than NGN uses Stripe
            if (!$plan->stripe_price_id) {
                throw new Exception("Stripe price ID not set for this plan.");
            }

            return $this->stripeService->createCheckoutSession(
                $user,
                $plan,
                $options['success_url'] ?? config('app.url') . '/payments/verify/stripe',
                $options['cancel_url'] ?? config('app.url') . '/plans'
            );
        }
    }
}

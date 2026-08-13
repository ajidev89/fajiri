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
        protected PayPalService $payPalService,
        protected FlutterwaveService $flutterwaveService,
        protected NombaService $nombaService,
        protected CurrencyService $currencyService
    ) {}

    public function getCurrencyService(): CurrencyService
    {
        return $this->currencyService;
    }

    public function getStripeService(): StripeService
    {
        return $this->stripeService;
    }

    public function getPaystackService(): PaystackService
    {
        return $this->paystackService;
    }

    public function getPayPalService(): PayPalService
    {
        return $this->payPalService;
    }

    public function getFlutterwaveService(): FlutterwaveService
    {
        return $this->flutterwaveService;
    }

    public function getNombaService(): NombaService
    {
        return $this->nombaService;
    }

    /**
     * Get gateway service by provider name or fallback by currency
     */
    public function getService(string $identifier)
    {
        return match (strtolower($identifier)) {
            'stripe' => $this->stripeService,
            'paystack' => $this->paystackService,
            'paypal' => $this->payPalService,
            'flutterwave', 'rave' => $this->flutterwaveService,
            'nomba' => $this->nombaService,
            'ngn' => $this->paystackService,
            default => $this->stripeService,
        };
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

            $paystackAmount = $plan->price;
            if (strtoupper($plan->currency ?? 'NGN') !== 'NGN') {
                $paystackAmount = $this->currencyService->convert(
                    (float) $plan->price,
                    $plan->currency ?? 'USD',
                    'NGN'
                );
            }

            return $this->paystackService->initializeSubscription([
                'email' => $user->email,
                'amount' => (int) round($paystackAmount * 100),
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

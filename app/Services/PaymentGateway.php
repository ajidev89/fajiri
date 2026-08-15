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
        $gateway = strtolower($options['gateway'] ?? $options['payment_method'] ?? request()->gateway ?? request()->payment_method ?? 'paystack');
        $currency = strtoupper($options['currency'] ?? $user->country->currency ?? request()->detected_currency ?? 'NGN');

        switch ($gateway) {
            case 'stripe':
                if (!$plan->stripe_price_id) {
                    throw new Exception("Stripe price ID not set for this plan.");
                }

                return $this->stripeService->createCheckoutSession(
                    $user,
                    $plan,
                    $options['success_url'] ?? config('app.url') . '/payments/verify/stripe',
                    $options['cancel_url'] ?? config('app.url') . '/plans'
                );

            case 'paypal':
                $paypalCurrency = $currency === 'NGN' ? 'USD' : $currency;
                $paypalAmount = $plan->price;
                if (strtoupper($plan->currency ?? 'USD') !== $paypalCurrency) {
                    $paypalAmount = $this->currencyService->convert(
                        (float) $plan->price,
                        $plan->currency ?? 'NGN',
                        $paypalCurrency
                    );
                }

                $order = $this->payPalService->createOrder([
                    'amount'      => (float) $paypalAmount,
                    'currency'    => $paypalCurrency,
                    'description' => "Subscription to {$plan->name} plan",
                    'reference'   => 'sub_' . $plan->id . '_' . $user->id . '_' . time(),
                    'return_url'  => $options['success_url'] ?? config('app.url') . '/payments/verify/paypal',
                    'cancel_url'  => $options['cancel_url'] ?? config('app.url') . '/plans',
                ]);

                $approveUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

                return [
                    'authorization_url' => $approveUrl,
                    'order_id'          => $order['id'] ?? null,
                    'raw'               => $order,
                ];

            case 'flutterwave':
            case 'rave':
                $flwAmount = $plan->price;
                if (strtoupper($plan->currency ?? 'NGN') !== $currency) {
                    $flwAmount = $this->currencyService->convert(
                        (float) $plan->price,
                        $plan->currency ?? 'NGN',
                        $currency
                    );
                }

                $result = $this->flutterwaveService->initializeTransaction([
                    'amount'       => (float) $flwAmount,
                    'currency'     => $currency,
                    'email'        => $user->email,
                    'name'         => $user->name ?? $user->profile?->first_name,
                    'phone'        => $user->phone ?? null,
                    'redirect_url' => $options['success_url'] ?? config('app.url') . '/payments/verify/flutterwave',
                    'title'        => "Subscription to {$plan->name}",
                    'description'  => "Contribution for {$plan->name} plan",
                    'meta'         => [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'type'    => 'subscription',
                    ],
                ]);

                return [
                    'authorization_url' => $result['link'] ?? null,
                    'reference'         => $result['tx_ref'] ?? null,
                    'data'              => $result,
                ];

            case 'nomba':
                $nombaAmount = $plan->price;
                if (strtoupper($plan->currency ?? 'NGN') !== 'NGN') {
                    $nombaAmount = $this->currencyService->convert(
                        (float) $plan->price,
                        $plan->currency ?? 'USD',
                        'NGN'
                    );
                }

                $result = $this->nombaService->createCheckoutOrder([
                    'amount'        => (float) $nombaAmount,
                    'currency'      => 'NGN',
                    'email'         => $user->email,
                    'name'          => $user->name ?? $user->profile?->first_name,
                    'callback_url'  => $options['success_url'] ?? config('app.url') . '/payments/verify/nomba',
                    'description'   => "Subscription to {$plan->name} plan",
                    'reference'     => 'nomba_sub_' . $user->id . '_' . $plan->id . '_' . time(),
                ]);

                return [
                    'authorization_url' => $result['checkoutUrl'] ?? $result['link'] ?? null,
                    'order_reference'   => $result['orderReference'] ?? null,
                    'data'              => $result,
                ];

            case 'paystack':
            default:
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
        }
    }
}

<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
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
                if (! $plan->stripe_price_id) {
                    throw new Exception('Stripe price ID not set for this plan.');
                }

                return $this->stripeService->createCheckoutSession(
                    $user,
                    $plan,
                    $options['success_url'] ?? config('app.url').'/payments/verify/stripe',
                    $options['cancel_url'] ?? config('app.url').'/plans'
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
                    'amount' => (float) $paypalAmount,
                    'currency' => $paypalCurrency,
                    'description' => "Subscription to {$plan->name} plan",
                    'reference' => 'sub_'.$plan->id.'_'.$user->id.'_'.time(),
                    'return_url' => $options['success_url'] ?? config('app.url').'/payments/verify/paypal',
                    'cancel_url' => $options['cancel_url'] ?? config('app.url').'/plans',
                ]);

                $approveUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

                return [
                    'authorization_url' => $approveUrl,
                    'order_id' => $order['id'] ?? null,
                    'raw' => $order,
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
                    'amount' => (float) $flwAmount,
                    'currency' => $currency,
                    'email' => $user->email,
                    'name' => $user->name ?? $user->profile?->first_name,
                    'phone' => $user->phone ?? null,
                    'redirect_url' => $options['success_url'] ?? config('app.url').'/payments/verify/flutterwave',
                    'title' => "Subscription to {$plan->name}",
                    'description' => "Contribution for {$plan->name} plan",
                    'meta' => [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'type' => 'subscription',
                    ],
                ]);

                return [
                    'authorization_url' => $result['link'] ?? null,
                    'reference' => $result['tx_ref'] ?? null,
                    'data' => $result,
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
                    'amount' => (float) $nombaAmount,
                    'currency' => 'NGN',
                    'email' => $user->email,
                    'name' => $user->name ?? $user->profile?->first_name,
                    'callback_url' => $options['success_url'] ?? config('app.url').'/payments/verify/nomba',
                    'description' => "Subscription to {$plan->name} plan",
                    'reference' => 'nomba_sub_'.$user->id.'_'.$plan->id.'_'.time(),
                ]);

                return [
                    'authorization_url' => $result['checkoutLink'] ?? $result['checkoutUrl'] ?? $result['link'] ?? null,
                    'order_reference' => $result['orderReference'] ?? null,
                    'data' => $result,
                ];

            case 'paystack':
            default:
                if (! $plan->paystack_plan_code) {
                    throw new Exception('Paystack plan code not set for this plan.');
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
                    'callback_url' => $options['success_url'] ?? config('app.url').'/payments/verify/paystack',
                    'metadata' => [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                        'type' => 'subscription',
                    ],
                ]);
        }
    }

    /**
     * Initialize a one-time donation checkout for the given gateway.
     *
     * @param  array{amount: float, currency: string, email: string, name?: string, reference: string, user_id?: string|null, donatable_id?: string|null, title?: string, callback_url?: string, cancel_url?: string}  $data
     * @return array{authorization_url: ?string, reference: string, access_code?: ?string, order_id?: ?string, order_reference?: ?string}
     */
    public function initializeDonation(string $gateway, array $data): array
    {
        $gateway = strtolower($gateway);
        $amount = (float) $data['amount'];
        $currency = strtoupper((string) $data['currency']);
        $email = $data['email'];
        $name = $data['name'] ?? $email;
        $reference = $data['reference'];
        $callbackUrl = $data['callback_url'] ?? config('app.url').'/donations/verify';
        $cancelUrl = $data['cancel_url'] ?? config('app.url');
        $title = $data['title'] ?? 'Donation';
        $metadata = [
            'donatable_id' => $data['donatable_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'type' => 'donation',
            'reference' => $reference,
        ];

        return match ($gateway) {
            'stripe' => $this->initializeStripeDonation($amount, $currency, $email, $data['user_id'] ?? null, $reference, $callbackUrl, $cancelUrl, $title, $metadata),
            'paypal' => $this->initializePaypalDonation($amount, $currency, $reference, $callbackUrl, $cancelUrl, $title),
            'flutterwave', 'rave' => $this->initializeFlutterwaveDonation($amount, $currency, $email, $name, $reference, $callbackUrl, $title, $metadata),
            'nomba' => $this->initializeNombaDonation($amount, $currency, $email, $name, $reference, $callbackUrl, $title),
            default => $this->initializePaystackDonation($amount, $currency, $email, $reference, $callbackUrl, $metadata),
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: ?string, reference: string, access_code?: ?string}
     */
    protected function initializePaystackDonation(float $amount, string $currency, string $email, string $reference, string $callbackUrl, array $metadata): array
    {
        $paystackAmount = $currency === 'NGN'
            ? $amount
            : $this->currencyService->convert($amount, $currency, 'NGN');

        $result = $this->paystackService->initializeTransaction([
            'amount' => (int) round($paystackAmount * 100),
            'email' => $email,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        return [
            'authorization_url' => $result['authorization_url'] ?? null,
            'access_code' => $result['access_code'] ?? null,
            'reference' => $result['reference'] ?? $reference,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: ?string, reference: string, access_code?: ?string}
     */
    protected function initializeStripeDonation(float $amount, string $currency, string $email, mixed $userId, string $reference, string $callbackUrl, string $cancelUrl, string $title, array $metadata): array
    {
        $session = $this->stripeService->createOneTimePaymentSession(
            (object) [
                'email' => $email,
                'id' => $userId,
            ],
            $amount,
            $currency,
            $callbackUrl,
            $cancelUrl,
            'Donation',
            $title,
            $metadata
        );

        return [
            'authorization_url' => $session->url ?? null,
            'access_code' => $session->id ?? null,
            'reference' => $session->id ?? $reference,
        ];
    }

    /**
     * @return array{authorization_url: ?string, reference: string, order_id?: ?string}
     */
    protected function initializePaypalDonation(float $amount, string $currency, string $reference, string $callbackUrl, string $cancelUrl, string $title): array
    {
        $paypalCurrency = $currency === 'NGN' ? 'USD' : $currency;
        $paypalAmount = $currency === 'NGN'
            ? $this->currencyService->convert($amount, $currency, $paypalCurrency)
            : $amount;

        $order = $this->payPalService->createOrder([
            'amount' => (float) $paypalAmount,
            'currency' => $paypalCurrency,
            'description' => $title,
            'reference' => $reference,
            'return_url' => $callbackUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $approveUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'authorization_url' => $approveUrl,
            'order_id' => $order['id'] ?? null,
            'reference' => $order['id'] ?? $reference,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: ?string, reference: string}
     */
    protected function initializeFlutterwaveDonation(float $amount, string $currency, string $email, string $name, string $reference, string $callbackUrl, string $title, array $metadata): array
    {
        $result = $this->flutterwaveService->initializeTransaction([
            'amount' => $amount,
            'currency' => $currency,
            'email' => $email,
            'name' => $name,
            'reference' => $reference,
            'redirect_url' => $callbackUrl,
            'title' => $title,
            'description' => $title,
            'meta' => $metadata,
        ]);

        return [
            'authorization_url' => $result['authorization_url'] ?? $result['link'] ?? null,
            'reference' => $result['reference'] ?? $result['tx_ref'] ?? $reference,
        ];
    }

    /**
     * @return array{authorization_url: ?string, reference: string, order_reference?: ?string}
     */
    protected function initializeNombaDonation(float $amount, string $currency, string $email, string $name, string $reference, string $callbackUrl, string $title): array
    {
        $nombaAmount = $currency === 'NGN'
            ? $amount
            : $this->currencyService->convert($amount, $currency, 'NGN');

        $result = $this->nombaService->createCheckoutOrder([
            'amount' => (float) $nombaAmount,
            'currency' => 'NGN',
            'email' => $email,
            'name' => $name,
            'callback_url' => $callbackUrl,
            'description' => $title,
            'reference' => $reference,
        ]);

        return [
            'authorization_url' => $result['checkoutLink'] ?? $result['checkoutUrl'] ?? $result['link'] ?? null,
            'order_reference' => $result['orderReference'] ?? null,
            'reference' => $result['orderReference'] ?? $reference,
        ];
    }
}

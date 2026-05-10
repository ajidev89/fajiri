<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;


class StripeService
{
    protected string $baseUrl = 'https://api.stripe.com/v1';
    protected string $secretKey;
    protected StripeClient $client;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->client = new StripeClient($this->secretKey);
    }

    /**
     * Create a Stripe Checkout Session for Subscription
     */
    public function createCheckoutSession($user, $plan, $successUrl, $cancelUrl)
    {
        $payload = [
            'customer_email' => $user->email,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
        ];
        return $this->client->checkout->sessions->create($payload);
    }

    /**
     * Create a Stripe Checkout Session for One-time Payment (Wallet Funding)
     */
    public function createOneTimePaymentSession($user, $amount, $currency, $successUrl, $cancelUrl, $metadata = [])
    {
        $payload = [
            'customer_email' => $user->email,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => 'Wallet Funding',
                        'description' => 'Funding your Fajiri wallet',
                    ],
                    'unit_amount' => $amount * 100, // Amount in cents
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => array_merge([
                'user_id' => $user->id,
                'type' => 'wallet_funding',
                'amount' => $amount,
                'currency' => strtoupper($currency)
            ], $metadata),
        ];
        return $this->client->checkout->sessions->create($payload);
    }

    /**
     * Create a Stripe product with a default price using the Stripe SDK.
     * Expected $data: ['name'=>string, 'unit_amount'=>int (cents), 'currency'=>string, 'description'=>string (optional)]
     */
    public function createProductWithDefaultPrice(array $data)
    {
        $params = [
            'name' => $data['name'],
            'default_price_data' => [
                'unit_amount' => $data['unit_amount'],
                'currency' => $data['currency'],
            ],
            'expand' => ['default_price'],
        ];
        if (!empty($data['description'] ?? null)) {
            $params['description'] = $data['description'];
        }
        return $this->client->products->create($params);
    }

    /**
     * Create a Stripe Product
     */
    public function createProduct(array $data)
    {
        // Use the Stripe SDK client to create a product.
        return $this->client->products->create($data);
    }

    /**
     * Create a Stripe Price
     */
    public function createPrice(array $data)
    {
        // Use the Stripe SDK client to create a price.
        return $this->client->prices->create($data);
    }

    /**
     * Get Session Details
     */
    public function getSession($sessionId)
    {
        return $this->client->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Get Subscription Details
     */
    public function getSubscription($subscriptionId)
    {
        return $this->client->subscriptions->retrieve($subscriptionId);
    }

    /**
     * Cancel Subscription
     */
    public function cancelSubscription($subscriptionId)
    {
        return $this->client->subscriptions->cancel($subscriptionId);
    }

    /**
     * Handle Webhook Signature Verification
     */
    public function isValidWebhook(string $signature, string $payload): bool
    {
        $endpointSecret = config('services.stripe.webhook');
        if (!$endpointSecret) {
            return false;
        }
        try {
            \Stripe\Webhook::constructEvent($payload, $signature, $endpointSecret);
            return true;
        } catch (Exception $e) {
            Log::error('Stripe webhook verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function request($method, $endpoint, $data = [])
    {
        $response = Http::withToken($this->secretKey)
            ->asForm()
            ->send($method, "{$this->baseUrl}/{$endpoint}", $data);

        if ($response->failed()) {
            Log::error('Stripe API Error', ['response' => $response->json(), 'endpoint' => $endpoint]);
            throw new Exception("Stripe error: " . ($response->json()['error']['message'] ?? $response->body()));
        }

        return $response->json();
    }

    // Existing methods from placeholder (kept for compatibility if needed elsewhere)
    public function createConnectedAccount($user)
    {
        return (object)['id' => 'acct_placeholder'];
    }

    public function bankAccount($user, array $data)
    {
        return (object)['id' => 'ba_placeholder'];
    }

    public function transfer(array $data)
    {
        return (object)['id' => 'tr_placeholder'];
    }
}

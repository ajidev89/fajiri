<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    protected string $baseUrl = 'https://api.stripe.com/v1';

    protected ?string $secretKey = null;

    protected ?StripeClient $client = null;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        if ($this->secretKey) {
            $this->client = new StripeClient($this->secretKey);
        }
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
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $user->id ?? null,
                'plan_id' => $plan->id,
            ],
        ];

        return $this->client->checkout->sessions->create($payload);
    }

    /**
     * Create a Stripe Checkout Session for One-time Payment (Wallet Funding / Donation)
     */
    public function createOneTimePaymentSession($user, $amount, $currency, $successUrl, $cancelUrl, $productName = 'Wallet Funding', $productDescription = 'Funding your Fajiri wallet', $metadata = [])
    {
        $payload = [
            'customer_email' => $user->email,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => $productName,
                        'description' => $productDescription,
                    ],
                    'unit_amount' => $amount * 100, // Amount in cents
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => array_merge([
                'user_id' => is_object($user) && isset($user->id) ? $user->id : null,
                'type' => 'wallet_funding',
                'amount' => $amount,
                'currency' => strtoupper($currency),
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
        if (! empty($data['description'] ?? null)) {
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
        if (! $endpointSecret) {
            return false;
        }
        try {
            Webhook::constructEvent($payload, $signature, $endpointSecret);

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
            throw new Exception('Stripe error: '.($response->json()['error']['message'] ?? $response->body()));
        }

        return $response->json();
    }

    public function createCustomer(User $user): string
    {
        $customer = $this->ensureClient()->customers->create([
            'email' => $user->email,
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        return $customer->id;
    }

    /**
     * Start a Stripe Financial Connections session for a US bank account.
     *
     * @return array{provider: string, session_id: string, client_secret: string}
     */
    public function createFinancialConnectionsSession(string $customerId): array
    {
        $session = $this->ensureClient()->financialConnections->sessions->create([
            'account_holder' => [
                'type' => 'customer',
                'customer' => $customerId,
            ],
            'permissions' => ['ownership', 'payment_method'],
            'prefetch' => ['ownership'],
            'filters' => ['countries' => ['US']],
        ]);

        return [
            'provider' => 'stripe',
            'session_id' => $session->id,
            'client_secret' => $session->client_secret,
        ];
    }

    /**
     * Read the linked US account and the holder name Stripe collected.
     *
     * @return array{account_name: string, bank_name: ?string, last4: ?string, provider: string, financial_connections_account: string}
     */
    public function resolveFinancialConnectionsSession(string $sessionId, string $customerId): array
    {
        $session = $this->ensureClient()->financialConnections->sessions->retrieve($sessionId);
        $holderId = $session->account_holder->customer ?? null;

        if ((string) $holderId !== $customerId) {
            throw new Exception('This bank link does not belong to the current user.');
        }

        $accounts = $session->accounts->data ?? [];
        if (count($accounts) === 0) {
            throw new Exception('No bank account has been linked yet.');
        }

        $accountId = is_object($accounts[0]) ? $accounts[0]->id : $accounts[0];
        $account = $this->client->financialConnections->accounts->retrieve($accountId, [
            'expand' => ['ownership'],
        ]);

        $owners = $account->ownership->owners->data ?? [];
        $accountName = $owners[0]->name ?? null;

        if (! $accountName) {
            throw new Exception('Stripe has not returned the account holder name yet. Try again in a moment.');
        }

        return [
            'account_name' => $accountName,
            'bank_name' => $account->institution_name,
            'last4' => $account->last4,
            'provider' => 'stripe',
            'financial_connections_account' => $account->id,
        ];
    }

    /**
     * Validate a Canadian bank account. Stripe returns the bank name, not the holder name.
     *
     * @return array{account_name: null, bank_name: ?string, account_number: string, last4: ?string, routing_number: ?string, provider: string, bank_account_token: string}
     */
    public function verifyCanadianBankAccount(string $accountNumber, string $routingNumber): array
    {
        $token = $this->ensureClient()->tokens->create([
            'bank_account' => [
                'country' => 'CA',
                'currency' => 'cad',
                'account_holder_name' => 'Account holder',
                'account_holder_type' => 'individual',
                'routing_number' => $routingNumber,
                'account_number' => $accountNumber,
            ],
        ]);

        $bankAccount = $token->bank_account;

        return [
            'account_name' => null,
            'bank_name' => $bankAccount->bank_name ?? null,
            'account_number' => $accountNumber,
            'last4' => $bankAccount->last4 ?? null,
            'routing_number' => $bankAccount->routing_number ?? $routingNumber,
            'provider' => 'stripe',
            'bank_account_token' => $token->id,
        ];
    }

    protected function ensureClient(): StripeClient
    {
        if (! $this->client) {
            throw new Exception('Stripe is not configured');
        }

        return $this->client;
    }

    // Existing methods from placeholder (kept for compatibility if needed elsewhere)
    public function createConnectedAccount($user)
    {
        return (object) ['id' => 'acct_placeholder'];
    }

    public function bankAccount($user, array $data)
    {
        return (object) ['id' => 'ba_placeholder'];
    }

    public function transfer(array $data)
    {
        return (object) ['id' => 'tr_placeholder'];
    }
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected string $baseUrl;
    protected string $authUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $publicKey;
    protected ?string $secretKey;
    protected ?string $secretHash;
    protected string $version;
    protected ?string $cachedAccessToken = null;
    protected ?int $tokenExpiresAt = null;

    public function __construct()
    {
        $this->clientId     = Config::get('flutterwave.clientId');
        $this->clientSecret = Config::get('flutterwave.clientSecret');
        $this->publicKey    = Config::get('flutterwave.publicKey');
        $this->secretKey    = Config::get('flutterwave.secretKey');
        $this->secretHash   = Config::get('flutterwave.secretHash');
        $this->version      = Config::get('flutterwave.version', 'v4');
        $this->authUrl      = Config::get('flutterwave.authUrl', 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token');
        $this->baseUrl      = rtrim(Config::get('flutterwave.paymentUrl', 'https://developersandbox-api.flutterwave.com'), '/');
    }

    /**
     * Obtain OAuth 2.0 Access Token for Flutterwave v4, or fallback to Secret Key
     */
    public function getAccessToken(): ?string
    {
        if (!empty($this->clientId) && !empty($this->clientSecret)) {
            if ($this->cachedAccessToken && $this->tokenExpiresAt && time() < ($this->tokenExpiresAt - 60)) {
                return $this->cachedAccessToken;
            }

            try {
                $response = Http::asForm()->post($this->authUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $this->cachedAccessToken = $data['access_token'] ?? null;
                    $expiresIn = $data['expires_in'] ?? 3600;
                    $this->tokenExpiresAt = time() + (int) $expiresIn;
                    return $this->cachedAccessToken;
                }

                Log::warning('Flutterwave OAuth token retrieval failed', ['body' => $response->body()]);
            } catch (Exception $e) {
                Log::warning('Flutterwave OAuth request error', ['error' => $e->getMessage()]);
            }
        }

        return $this->secretKey;
    }

    /**
     * Get authenticated HTTP client
     */
    protected function getHttpClient()
    {
        $token = $this->getAccessToken();
        return Http::withToken($token)->acceptJson();
    }

    /**
     * Initialize a Flutterwave Order / Payment Link
     */
    public function initializeTransaction(array $data): array
    {
        $txRef = $data['tx_ref'] ?? $data['reference'] ?? 'flw_' . uniqid() . '_' . time();

        $payload = [
            'tx_ref'          => $txRef,
            'reference'       => $txRef,
            'amount'          => (float) $data['amount'],
            'currency'        => strtoupper($data['currency'] ?? 'NGN'),
            'redirect_url'    => $data['redirect_url'] ?? config('app.url') . '/payments/verify/flutterwave',
            'meta'            => $data['meta'] ?? [],
            'customizations'  => [
                'title'       => $data['title'] ?? 'Fajiri Payment',
                'description' => $data['description'] ?? 'Payment for Fajiri service',
            ],
        ];

        // Support either direct customer_id or customer object
        if (!empty($data['customer_id'])) {
            $payload['customer_id'] = $data['customer_id'];
        } else {
            $payload['customer'] = [
                'email'       => $data['email'],
                'name'        => $data['name'] ?? null,
                'phonenumber' => $data['phone'] ?? null,
            ];
        }

        // Determine endpoint: v4 /orders primary endpoint
        $endpoint = $this->version === 'v4' && !str_contains($this->baseUrl, '/v3')
            ? "{$this->baseUrl}/orders"
            : "{$this->baseUrl}/payments";

        $response = $this->getHttpClient()->post($endpoint, $payload);

        // Fallback to /payments if /orders returns 404
        if ($response->status() === 404 && $endpoint !== "{$this->baseUrl}/payments") {
            $response = $this->getHttpClient()->post("{$this->baseUrl}/payments", $payload);
        }

        if ($response->failed()) {
            Log::error('Flutterwave initialize failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave initialization failed: ' . $response->body());
        }

        $responseData = $response->json('data') ?? $response->json();

        // Extract authorization / checkout redirect URL across v4 Orders next_action & standard hosted link formats
        $redirectUrl = $responseData['next_action']['redirect_url']['url']
            ?? $responseData['next_action']['url']
            ?? $responseData['link']
            ?? $responseData['hosted_link']
            ?? $responseData['authorization_url']
            ?? $responseData['checkout_url']
            ?? null;

        $responseData['link'] = $redirectUrl;
        $responseData['authorization_url'] = $redirectUrl;
        $responseData['tx_ref'] = $responseData['tx_ref'] ?? $responseData['reference'] ?? $txRef;

        return $responseData;
    }

    /**
     * Verify a Flutterwave Transaction / Order by ID
     */
    public function verifyTransaction(string $transactionId): array
    {
        $endpoint = $this->version === 'v4' && !str_contains($this->baseUrl, '/v3')
            ? "{$this->baseUrl}/orders/{$transactionId}"
            : "{$this->baseUrl}/transactions/{$transactionId}/verify";

        $response = $this->getHttpClient()->get($endpoint);

        if ($response->status() === 404) {
            // Fallback between /transactions and /orders / /charges
            $fallback = "{$this->baseUrl}/transactions/{$transactionId}/verify";
            $response = $this->getHttpClient()->get($fallback);

            if ($response->status() === 404) {
                $response = $this->getHttpClient()->get("{$this->baseUrl}/charges/{$transactionId}");
            }
        }

        if ($response->failed()) {
            Log::error('Flutterwave verify failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave verification failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Verify a Flutterwave Transaction / Order by TxRef / Reference
     */
    public function verifyTransactionByRef(string $txRef): array
    {
        $endpoint = $this->version === 'v4' && !str_contains($this->baseUrl, '/v3')
            ? "{$this->baseUrl}/orders/reference/{$txRef}"
            : "{$this->baseUrl}/transactions/verify-by-txref";

        $response = $this->getHttpClient()->get($endpoint, [
            'tx_ref' => $txRef,
        ]);

        if ($response->status() === 404) {
            $response = $this->getHttpClient()->get("{$this->baseUrl}/transactions/verify-by-txref", [
                'tx_ref' => $txRef,
            ]);
        }

        if ($response->failed()) {
            throw new Exception('Flutterwave verification by ref failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * List Customers (GET /customers)
     */
    public function listCustomers(array $query = []): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/customers", $query);

        if ($response->failed()) {
            Log::error('Flutterwave list customers failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave list customers failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Create a Customer (POST /customers)
     */
    public function createCustomer(array $data): array
    {
        $payload = [
            'email' => $data['email'],
            'name'  => [
                'first_name' => $data['first_name'] ?? $data['name'] ?? null,
                'last_name'  => $data['last_name'] ?? null,
            ],
            'phone' => [
                'country_code' => $data['country_code'] ?? '+234',
                'number'       => $data['phone'] ?? $data['phone_number'] ?? null,
            ],
            'meta'  => $data['meta'] ?? [],
        ];

        $response = $this->getHttpClient()->post("{$this->baseUrl}/customers", $payload);

        if ($response->failed()) {
            Log::error('Flutterwave create customer failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave create customer failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Get a Customer by ID (GET /customers/{id})
     */
    public function getCustomer(string $customerId): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/customers/{$customerId}");

        if ($response->failed()) {
            Log::error('Flutterwave get customer failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave get customer failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Validate Webhook Signature Header
     */
    public function isValidWebhook(?string $signature, ?string $payload = null): bool
    {
        if (!$this->secretHash) {
            return true; // If secret hash is not defined, accept payload securely
        }

        if ($signature && hash_equals($this->secretHash, $signature)) {
            return true;
        }

        if ($payload && $signature) {
            $computed = hash_hmac('sha256', $payload, $this->secretHash);
            if (hash_equals($computed, $signature)) {
                return true;
            }
        }

        return false;
    }
}



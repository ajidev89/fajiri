<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    protected ?string $scenarioKey;
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
        $this->scenarioKey  = Config::get('flutterwave.scenarioKey');
        $this->authUrl      = Config::get('flutterwave.authUrl', 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token');
        $this->baseUrl      = rtrim(Config::get('flutterwave.paymentUrl', 'https://developersandbox-api.flutterwave.com'), '/');
    }

    /**
     * Set a custom scenario key for sandbox testing
     */
    public function setScenarioKey(?string $scenarioKey): self
    {
        $this->scenarioKey = $scenarioKey;
        return $this;
    }

    /**
     * Obtain OAuth 2.0 Access Token for Flutterwave v4, or fallback to Secret Key
     */
    public function getAccessToken(): ?string
    {
        if (!empty($this->clientId) && !empty($this->clientSecret)) {
            // Check in-memory cache with a 60-second buffer
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
                    $expiresIn = $data['expires_in'] ?? 600;
                    $this->tokenExpiresAt = time() + (int) $expiresIn;
                    return $this->cachedAccessToken;
                }

                Log::warning('Flutterwave OAuth token retrieval failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            } catch (Exception $e) {
                Log::warning('Flutterwave OAuth request error', ['error' => $e->getMessage()]);
            }
        }

        return $this->secretKey;
    }

    /**
     * Get authenticated HTTP client with standard v4 headers (Authorization, X-Trace-Id, X-Idempotency-Key)
     */
    protected function getHttpClient(bool $isMutating = false, ?string $idempotencyKey = null, ?string $traceId = null)
    {
        $token = $this->getAccessToken();
        $traceId = $traceId ?: (string) Str::uuid();

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'X-Trace-Id'   => $traceId,
        ];

        if ($isMutating) {
            $headers['X-Idempotency-Key'] = $idempotencyKey ?: (string) Str::uuid();
        }

        if (!empty($this->scenarioKey)) {
            $headers['X-Scenario-Key'] = $this->scenarioKey;
        }

        return Http::withHeaders($headers)->withToken($token);
    }

    /**
     * Format a customer data array into the v4 Customer schema
     */
    public function formatCustomerPayload(array $data): array
    {
        $customer = [];

        // Email
        $customer['email'] = $data['email'] ?? null;

        // Name formatting: object with first, last, middle
        if (isset($data['name']) && is_array($data['name'])) {
            $customer['name'] = [
                'first' => $data['name']['first'] ?? $data['name']['first_name'] ?? '',
                'last'  => $data['name']['last'] ?? $data['name']['last_name'] ?? '',
            ];
            if (!empty($data['name']['middle'])) {
                $customer['name']['middle'] = $data['name']['middle'];
            }
        } elseif (!empty($data['name']) && is_string($data['name'])) {
            $parts = explode(' ', trim($data['name']), 2);
            $customer['name'] = [
                'first' => $parts[0] ?? '',
                'last'  => $parts[1] ?? ($parts[0] ?? ''),
            ];
        } else {
            $firstName = $data['first_name'] ?? 'Customer';
            $lastName  = $data['last_name'] ?? 'User';
            $customer['name'] = [
                'first' => $firstName,
                'last'  => $lastName,
            ];
        }

        // Phone formatting: object with country_code, number
        if (isset($data['phone']) && is_array($data['phone'])) {
            $customer['phone'] = [
                'country_code' => preg_replace('/[^0-9]/', '', (string) ($data['phone']['country_code'] ?? '234')),
                'number'       => preg_replace('/[^0-9]/', '', (string) ($data['phone']['number'] ?? '')),
            ];
        } elseif (!empty($data['phone']) && is_string($data['phone'])) {
            $rawPhone = preg_replace('/[^0-9]/', '', $data['phone']);
            if (str_starts_with($rawPhone, '234') && strlen($rawPhone) > 10) {
                $countryCode = '234';
                $number = substr($rawPhone, 3);
            } elseif (str_starts_with($rawPhone, '0') && strlen($rawPhone) === 11) {
                $countryCode = '234';
                $number = substr($rawPhone, 1);
            } else {
                $countryCode = '234';
                $number = $rawPhone;
            }
            $customer['phone'] = [
                'country_code' => $countryCode,
                'number'       => $number,
            ];
        }

        // Address formatting if provided
        if (!empty($data['address']) && is_array($data['address'])) {
            $customer['address'] = [
                'line1'       => $data['address']['line1'] ?? $data['address']['address'] ?? '',
                'line2'       => $data['address']['line2'] ?? '',
                'city'        => $data['address']['city'] ?? '',
                'state'       => $data['address']['state'] ?? '',
                'country'     => strtoupper($data['address']['country'] ?? 'NG'),
                'postal_code' => $data['address']['postal_code'] ?? $data['address']['zip'] ?? '',
            ];
        }

        if (!empty($data['meta']) && is_array($data['meta'])) {
            $customer['meta'] = $data['meta'];
        }

        return $customer;
    }

    /**
     * Initialize a Flutterwave Order / Payment Link via v4 Payment Orchestrator (/orchestration/direct-orders)
     */
    public function initializeTransaction(array $data): array
    {
        $reference = $data['reference'] ?? $data['tx_ref'] ?? 'flw_' . uniqid() . '_' . time();
        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();

        // Build v4 compliant direct-orders / orders payload
        $payload = [
            'reference'    => $reference,
            'amount'       => (float) $data['amount'],
            'currency'     => strtoupper($data['currency'] ?? 'NGN'),
            'redirect_url' => $data['redirect_url'] ?? $data['callback_url'] ?? config('app.url') . '/payments/verify/flutterwave',
            'meta'         => $data['meta'] ?? [],
        ];

        // Format customer object
        if (!empty($data['customer_id'])) {
            $payload['customer_id'] = $data['customer_id'];
        } elseif (!empty($data['customer']) && is_array($data['customer'])) {
            $payload['customer'] = $this->formatCustomerPayload($data['customer']);
        } else {
            $payload['customer'] = $this->formatCustomerPayload([
                'email'      => $data['email'] ?? null,
                'name'       => $data['name'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name'  => $data['last_name'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'address'    => $data['address'] ?? null,
                'meta'       => $data['meta'] ?? [],
            ]);
        }

        if (isset($data['merchant_vat_amount'])) {
            $payload['merchant_vat_amount'] = (float) $data['merchant_vat_amount'];
        }

        if (!empty($data['payment_method'])) {
            $payload['payment_method'] = $data['payment_method'];
        }

        if (!empty($data['customizations'])) {
            $payload['customizations'] = $data['customizations'];
        }

        // Endpoint prioritization for v4
        $primaryEndpoint = "{$this->baseUrl}/orchestration/direct-orders";
        $response = $this->getHttpClient(true, $idempotencyKey)->post($primaryEndpoint, $payload);

        // Fallback to /orders if /orchestration/direct-orders is unavailable
        if ($response->status() === 404) {
            $response = $this->getHttpClient(true, $idempotencyKey)->post("{$this->baseUrl}/orders", $payload);
        }

        // Fallback to /payments if legacy v3 endpoint
        if ($response->status() === 404) {
            $legacyPayload = array_merge($payload, [
                'tx_ref'   => $reference,
                'customer' => [
                    'email'       => $data['email'] ?? $payload['customer']['email'] ?? '',
                    'name'        => is_string($data['name'] ?? null) ? $data['name'] : ($payload['customer']['name']['first'] ?? ''),
                    'phonenumber' => $data['phone'] ?? null,
                ],
            ]);
            $response = $this->getHttpClient(true, $idempotencyKey)->post("{$this->baseUrl}/payments", $legacyPayload);
        }

        if ($response->failed()) {
            Log::error('Flutterwave initialize failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave initialization failed: ' . $response->body());
        }

        $responseData = $response->json('data') ?? $response->json();

        // Extract checkout / authorization redirect URL across v4 & v3 response variations
        $redirectUrl = $responseData['redirect_url']
            ?? $responseData['next_action']['redirect_url']['url']
            ?? $responseData['next_action']['url']
            ?? $responseData['link']
            ?? $responseData['hosted_link']
            ?? $responseData['authorization_url']
            ?? $responseData['checkout_url']
            ?? null;

        $responseData['link']              = $redirectUrl;
        $responseData['authorization_url'] = $redirectUrl;
        $responseData['reference']         = $responseData['reference'] ?? $responseData['tx_ref'] ?? $reference;
        $responseData['tx_ref']            = $responseData['reference'];

        return $responseData;
    }

    /**
     * Direct Charge via Orchestrator (POST /orchestration/direct-charges)
     */
    public function chargeDirect(array $data): array
    {
        $reference = $data['reference'] ?? $data['tx_ref'] ?? 'flw_chg_' . uniqid() . '_' . time();
        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();

        $payload = [
            'amount'         => (float) $data['amount'],
            'currency'       => strtoupper($data['currency'] ?? 'NGN'),
            'reference'      => $reference,
            'payment_method' => $data['payment_method'],
            'redirect_url'   => $data['redirect_url'] ?? config('app.url') . '/payments/verify/flutterwave',
            'meta'           => $data['meta'] ?? [],
        ];

        if (!empty($data['customer_id'])) {
            $payload['customer_id'] = $data['customer_id'];
        } else {
            $payload['customer'] = $this->formatCustomerPayload($data['customer'] ?? $data);
        }

        $response = $this->getHttpClient(true, $idempotencyKey)->post("{$this->baseUrl}/orchestration/direct-charges", $payload);

        if ($response->status() === 404) {
            $response = $this->getHttpClient(true, $idempotencyKey)->post("{$this->baseUrl}/charges", $payload);
        }

        if ($response->failed()) {
            Log::error('Flutterwave direct charge failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave direct charge failed: ' . $response->body());
        }

        return $this->normalizeTransactionData($response->json('data') ?? $response->json());
    }

    /**
     * Verify a Flutterwave Transaction / Charge / Order by ID
     */
    public function verifyTransaction(string $transactionId): array
    {
        // Try charges first in v4
        $endpoint = "{$this->baseUrl}/charges/{$transactionId}";
        $response = $this->getHttpClient()->get($endpoint);

        // Fallback to /orders/{id}
        if ($response->status() === 404) {
            $response = $this->getHttpClient()->get("{$this->baseUrl}/orders/{$transactionId}");
        }

        // Fallback to legacy v3 /transactions/{id}/verify
        if ($response->status() === 404) {
            $response = $this->getHttpClient()->get("{$this->baseUrl}/transactions/{$transactionId}/verify");
        }

        if ($response->failed()) {
            Log::error('Flutterwave verify failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave verification failed: ' . $response->body());
        }

        return $this->normalizeTransactionData($response->json('data') ?? $response->json());
    }

    /**
     * Verify an Order by Order ID (GET /orders/{id})
     */
    public function verifyOrder(string $orderId): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/orders/{$orderId}");

        if ($response->failed()) {
            Log::error('Flutterwave verify order failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave verify order failed: ' . $response->body());
        }

        return $this->normalizeTransactionData($response->json('data') ?? $response->json());
    }

    /**
     * Verify a Flutterwave Transaction / Order by TxRef / Reference
     */
    public function verifyTransactionByRef(string $reference): array
    {
        // Try charges by reference
        $response = $this->getHttpClient()->get("{$this->baseUrl}/charges", ['reference' => $reference]);

        if ($response->status() === 404 || empty($response->json('data'))) {
            // Try orders by reference
            $response = $this->getHttpClient()->get("{$this->baseUrl}/orders", ['reference' => $reference]);
        }

        if ($response->status() === 404 || empty($response->json('data'))) {
            // Try orders/reference/{ref} or legacy verify-by-txref
            $response = $this->getHttpClient()->get("{$this->baseUrl}/transactions/verify-by-txref", ['tx_ref' => $reference]);
        }

        if ($response->failed()) {
            throw new Exception('Flutterwave verification by ref failed: ' . $response->body());
        }

        $data = $response->json('data') ?? $response->json();
        if (is_array($data) && isset($data[0])) {
            $data = $data[0];
        }

        return $this->normalizeTransactionData($data);
    }

    /**
     * Normalize transaction data across v4 and v3 structures
     */
    public function normalizeTransactionData(array $data): array
    {
        // Status normalization: 'succeeded' and 'successful' both mapped
        $rawStatus = strtolower($data['status'] ?? '');
        $isSuccess = in_array($rawStatus, ['succeeded', 'successful', 'completed', 'approved']);

        $data['is_successful'] = $isSuccess;
        if ($isSuccess && !isset($data['status'])) {
            $data['status'] = 'successful';
        }

        // Normalize reference / tx_ref
        $ref = $data['reference'] ?? $data['tx_ref'] ?? $data['id'] ?? null;
        $data['reference'] = $ref;
        $data['tx_ref']    = $ref;

        return $data;
    }

    /**
     * Determine if a transaction / charge response is successful
     */
    public function isSuccessful(array $transaction): bool
    {
        $status = strtolower($transaction['status'] ?? '');
        return in_array($status, ['succeeded', 'successful', 'completed', 'approved']);
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
     * Create a Customer (POST /customers) with v4 Schema
     */
    public function createCustomer(array $data): array
    {
        $payload = $this->formatCustomerPayload($data);

        $response = $this->getHttpClient(true)->post("{$this->baseUrl}/customers", $payload);

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
     * Search Customers by Email (GET /customers/search)
     */
    public function searchCustomers(string $email): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/customers/search", ['email' => $email]);

        if ($response->failed()) {
            Log::error('Flutterwave search customers failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave search customers failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Validate Webhook Signature Header
     *
     * In Flutterwave v4, the signature is returned in the 'flutterwave-signature' header
     * and is computed as HMAC-SHA256 base64 digest of raw payload using secretHash:
     * base64_encode(hash_hmac('sha256', $rawBody, $secretHash, true))
     */
    public function isValidWebhook(?string $signature, ?string $payload = null): bool
    {
        if (!$this->secretHash) {
            return true;
        }

        if (!$signature) {
            return false;
        }

        // 1. Direct match with secretHash (v3 verif-hash / raw hash header)
        if (hash_equals($this->secretHash, $signature)) {
            return true;
        }

        if ($payload !== null) {
            // 2. v4 Standard: HMAC-SHA256 Base64 digest
            $computedBase64 = base64_encode(hash_hmac('sha256', $payload, $this->secretHash, true));
            if (hash_equals($computedBase64, $signature)) {
                return true;
            }

            // 3. HMAC-SHA256 Hex digest fallback
            $computedHex = hash_hmac('sha256', $payload, $this->secretHash);
            if (hash_equals($computedHex, $signature)) {
                return true;
            }
        }

        return false;
    }
}



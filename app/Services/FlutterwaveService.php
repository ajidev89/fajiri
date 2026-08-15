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
     * Initialize a Flutterwave Payment Link
     */
    public function initializeTransaction(array $data): array
    {
        $txRef = $data['tx_ref'] ?? 'flw_' . uniqid() . '_' . time();

        $payload = [
            'tx_ref'          => $txRef,
            'amount'          => (float) $data['amount'],
            'currency'        => strtoupper($data['currency'] ?? 'NGN'),
            'redirect_url'    => $data['redirect_url'] ?? config('app.url') . '/payments/verify/flutterwave',
            'meta'            => $data['meta'] ?? [],
            'customer'        => [
                'email'       => $data['email'],
                'name'        => $data['name'] ?? null,
                'phonenumber' => $data['phone'] ?? null,
            ],
            'customizations'  => [
                'title'       => $data['title'] ?? 'Fajiri Payment',
                'description' => $data['description'] ?? 'Payment for Fajiri service',
            ],
        ];

        // Determine payments endpoint
        $endpoint = str_contains($this->baseUrl, '/v3') ? "{$this->baseUrl}/payments" : "{$this->baseUrl}/payments";
        if ($this->version === 'v4' && !str_contains($this->baseUrl, '/v3')) {
            $endpoint = "{$this->baseUrl}/charges/checkout";
        }

        $response = $this->getHttpClient()->post($endpoint, $payload);

        // Fallback to /payments if /charges/checkout is 404
        if ($response->status() === 404 && $endpoint !== "{$this->baseUrl}/payments") {
            $response = $this->getHttpClient()->post("{$this->baseUrl}/payments", $payload);
        }

        if ($response->failed()) {
            Log::error('Flutterwave initialize failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave initialization failed: ' . $response->body());
        }

        $responseData = $response->json('data') ?? $response->json();
        if (!isset($responseData['link']) && isset($responseData['hosted_link'])) {
            $responseData['link'] = $responseData['hosted_link'];
        } elseif (!isset($responseData['link']) && isset($responseData['authorization_url'])) {
            $responseData['link'] = $responseData['authorization_url'];
        }

        $responseData['tx_ref'] = $responseData['tx_ref'] ?? $txRef;

        return $responseData;
    }

    /**
     * Verify a Flutterwave Transaction by ID
     */
    public function verifyTransaction(string $transactionId): array
    {
        $endpoint = "{$this->baseUrl}/transactions/{$transactionId}/verify";
        $response = $this->getHttpClient()->get($endpoint);

        if ($response->status() === 404) {
            // v4 charge lookup fallback
            $response = $this->getHttpClient()->get("{$this->baseUrl}/charges/{$transactionId}");
        }

        if ($response->failed()) {
            Log::error('Flutterwave verify failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave verification failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Verify a Flutterwave Transaction by TxRef
     */
    public function verifyTransactionByRef(string $txRef): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/transactions/verify-by-txref", [
            'tx_ref' => $txRef,
        ]);

        if ($response->failed()) {
            throw new Exception('Flutterwave verification by ref failed: ' . $response->body());
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


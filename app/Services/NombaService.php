<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NombaService
{
    protected string $baseUrl;
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected ?string $accountId;

    public function __construct()
    {
        $this->baseUrl      = Config::get('nomba.baseUrl', 'https://api.nomba.com/v1');
        $this->clientId     = Config::get('nomba.clientId');
        $this->clientSecret = Config::get('nomba.clientSecret');
        $this->accountId    = Config::get('nomba.accountId');
    }

    /**
     * Authenticate and retrieve Access Token from Nomba
     */
    public function getAccessToken(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($response->failed()) {
            Log::error('Nomba auth failed', ['body' => $response->body()]);
            throw new Exception('Nomba authentication failed: ' . $response->body());
        }

        return $response->json('data.access_token');
    }

    /**
     * Create a Checkout Order / Payment Link with Nomba
     */
    public function createCheckoutOrder(array $data): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'orderReference' => $data['reference'] ?? 'nomba_' . uniqid() . '_' . time(),
            'amount'         => (float) $data['amount'],
            'currency'       => strtoupper($data['currency'] ?? 'NGN'),
            'accountId'      => $this->accountId,
            'callbackUrl'    => $data['callback_url'] ?? config('app.url') . '/payments/verify/nomba',
            'customerEmail'  => $data['email'],
            'customerName'   => $data['name'] ?? null,
            'description'    => $data['description'] ?? 'Payment for Fajiri',
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/checkout/order", $payload);

        if ($response->failed()) {
            Log::error('Nomba checkout order failed', ['body' => $response->body()]);
            throw new Exception('Nomba order creation failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Get Nomba Order Status by Reference or Order ID
     */
    public function getOrderStatus(string $reference): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/checkout/order/reference/{$reference}");

        if ($response->failed()) {
            Log::error('Nomba verify failed', ['body' => $response->body()]);
            throw new Exception('Nomba verification failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Validate Webhook Signature
     */
    public function isValidWebhook(?string $signature, string $payload): bool
    {
        if (!$signature || !$this->clientSecret) {
            return true;
        }

        $computedSignature = hash_hmac('sha256', $payload, $this->clientSecret);
        return hash_equals($computedSignature, $signature);
    }
}

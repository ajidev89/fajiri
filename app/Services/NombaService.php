<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NombaService
{
    protected string $baseUrl;

    protected string $mode;

    protected ?string $clientId = null;

    protected ?string $clientSecret = null;

    protected ?string $accountId = null;

    public function __construct()
    {
        $this->mode = Config::get('nomba.mode', 'test');
        $this->baseUrl = rtrim((string) Config::get('nomba.baseUrl', 'https://sandbox.nomba.com'), '/');
        $this->clientId = Config::get('nomba.clientId');
        $this->clientSecret = Config::get('nomba.clientSecret');
        $this->accountId = Config::get('nomba.accountId');
    }

    /**
     * Authenticate and retrieve Access Token from Nomba
     */
    public function getAccessToken(): string
    {
        return Cache::remember('nomba:access_token:'.$this->mode, now()->addMinutes(25), function () {
            $response = Http::withHeaders($this->headers())
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/v1/auth/token/issue", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if ($response->failed()) {
                Log::error('Nomba auth failed', [
                    'url' => "{$this->baseUrl}/v1/auth/token/issue",
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new Exception('Nomba authentication failed: '.$response->body());
            }

            $token = $response->json('data.access_token');

            if (! $token) {
                throw new Exception('Nomba authentication failed: access token missing from response');
            }

            return $token;
        });
    }

    /**
     * Create a Checkout Order / Payment Link with Nomba
     */
    public function createCheckoutOrder(array $data): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'order' => [
                'orderReference' => $data['reference'] ?? 'nomba_'.uniqid().'_'.time(),
                'amount' => number_format((float) $data['amount'], 2, '.', ''),
                'currency' => strtoupper($data['currency'] ?? 'NGN'),
                'callbackUrl' => $data['callback_url'] ?? config('app.url').'/payments/verify/nomba',
                'customerEmail' => $data['email'],
            ],
        ];

        $response = Http::withToken($token)
            ->withHeaders($this->headers())
            ->acceptJson()
            ->asJson()
            ->post($this->checkoutUrl('/order'), $payload);

        if ($response->failed()) {
            Log::error('Nomba checkout order failed', ['body' => $response->body()]);
            throw new Exception('Nomba order creation failed: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Get Nomba Order Status by Reference or Order ID
     */
    public function getOrderStatus(string $reference): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders($this->headers())
            ->acceptJson()
            ->get($this->checkoutUrl('/order/reference/'.$reference));

        if ($response->failed()) {
            Log::error('Nomba verify failed', ['body' => $response->body()]);
            throw new Exception('Nomba verification failed: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Validate Webhook Signature
     */
    public function isValidWebhook(?string $signature, string $payload): bool
    {
        if (! $signature || ! $this->clientSecret) {
            return true;
        }

        $computedSignature = hash_hmac('sha256', $payload, $this->clientSecret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return array_filter([
            'accountId' => $this->accountId,
        ]);
    }

    protected function checkoutUrl(string $path): string
    {
        $prefix = $this->mode === 'live' ? '/v1/checkout' : '/sandbox/checkout';

        return $this->baseUrl.$prefix.$path;
    }
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $this->assertConfigured();

        return Cache::remember('nomba:access_token:'.$this->mode.':'.$this->accountId, now()->addMinutes(25), function () {
            $response = $this->request()
                ->asJson()
                ->post("{$this->baseUrl}/v1/auth/token/issue", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            $body = $this->decoded($response, 'authentication');
            $token = $body['data']['access_token'] ?? null;

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

        $order = [
            'orderReference' => $this->orderReference($data['reference'] ?? null),
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => strtoupper($data['currency'] ?? 'NGN'),
            'callbackUrl' => $data['callback_url'] ?? config('app.url').'/payments/verify/nomba',
            'customerEmail' => $data['email'],
        ];

        $metadata = $this->orderMetadata($data['metadata'] ?? []);

        if ($metadata !== []) {
            $order['orderMetaData'] = $metadata;
        }

        $payload = [
            'order' => $order,
        ];

        $response = $this->request()
            ->withToken($token)
            ->asJson()
            ->post($this->checkoutUrl('/order'), $payload);

        return $this->decoded($response, 'order creation')['data'] ?? [];
    }

    /**
     * Get Nomba Order Status by Reference or Order ID
     */
    public function getOrderStatus(string $reference): array
    {
        $token = $this->getAccessToken();

        $response = $this->request()
            ->withToken($token)
            ->get($this->checkoutUrl('/order/reference/'.$reference));

        return $this->decoded($response, 'verification')['data'] ?? [];
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

    protected function assertConfigured(): void
    {
        if (! filled($this->clientId) || ! filled($this->clientSecret) || ! filled($this->accountId)) {
            throw new Exception('Nomba is not configured. Set NOMBA_CLIENT_ID, NOMBA_CLIENT_SECRET, and NOMBA_ACCOUNT_ID. NOMBA_ACCOUNT_ID must be the parent account id, and NOMBA_MODE=live only works with live keys on https://api.nomba.com.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decoded(Response $response, string $action): array
    {
        $body = $response->json() ?? [];
        $code = (string) ($body['code'] ?? '');
        $description = (string) ($body['description'] ?? $response->body());

        if ($response->failed() || $code !== '00') {
            Log::error('Nomba '.$action.' failed', [
                'url' => $this->baseUrl,
                'mode' => $this->mode,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception("Nomba {$action} failed ({$response->status()}): {$description}");
        }

        return $body;
    }

    protected function request(): PendingRequest
    {
        $options = [];

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $options['curl'] = [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ];
        }

        return Http::withOptions($options)
            ->withHeaders($this->headers())
            ->acceptJson();
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

    protected function orderReference(?string $reference): string
    {
        if ($reference !== null && strlen($reference) <= 50) {
            return $reference;
        }

        return 'nomba_'.Str::lower((string) Str::ulid());
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    protected function orderMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $clean[(string) $key] = (string) $value;
        }

        return $clean;
    }

    protected function checkoutUrl(string $path): string
    {
        $prefix = $this->mode === 'live' ? '/v1/checkout' : '/sandbox/checkout';

        return $this->baseUrl.$prefix.$path;
    }
}

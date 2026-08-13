<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    protected string $baseUrl;
    protected ?string $clientId;
    protected ?string $secret;
    protected ?string $webhookId;

    public function __construct()
    {
        $this->baseUrl   = Config::get('paypal.baseUrl', 'https://api-m.sandbox.paypal.com');
        $this->clientId  = Config::get('paypal.clientId');
        $this->secret    = Config::get('paypal.secret');
        $this->webhookId = Config::get('paypal.webhookId');
    }

    /**
     * Get OAuth2 Access Token from PayPal
     */
    public function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('PayPal auth failed', ['body' => $response->body()]);
            throw new Exception('PayPal authentication failed: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Create PayPal Order for Checkout
     */
    public function createOrder(array $data): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                        'value'         => number_format((float) $data['amount'], 2, '.', ''),
                    ],
                    'description' => $data['description'] ?? 'Payment for Fajiri',
                    'custom_id'   => $data['reference'] ?? null,
                ],
            ],
            'application_context' => [
                'return_url' => $data['return_url'] ?? config('app.url') . '/payments/verify/paypal',
                'cancel_url' => $data['cancel_url'] ?? config('app.url') . '/plans',
            ],
        ];

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders", $payload);

        if ($response->failed()) {
            Log::error('PayPal create order failed', ['body' => $response->body()]);
            throw new Exception('PayPal order creation failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Capture Payment for an approved PayPal Order
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withBody('', 'application/json')
            ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        if ($response->failed()) {
            Log::error('PayPal capture failed', ['body' => $response->body()]);
            throw new Exception('PayPal capture failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get Order Details
     */
    public function getOrderDetails(string $orderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/v2/checkout/orders/{$orderId}");

        if ($response->failed()) {
            throw new Exception('PayPal get order details failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Validate Webhook Notification Signature
     */
    public function isValidWebhook(array $headers, string $payload): bool
    {
        if (!$this->webhookId) {
            return false;
        }

        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                    'auth_algo'         => $headers['paypal-auth-algo'][0] ?? $headers['paypal-auth-algo'] ?? '',
                    'cert_url'          => $headers['paypal-cert-url'][0] ?? $headers['paypal-cert-url'] ?? '',
                    'client_id'         => $this->clientId,
                    'transmission_id'   => $headers['paypal-transmission-id'][0] ?? $headers['paypal-transmission-id'] ?? '',
                    'transmission_sig'  => $headers['paypal-transmission-sig'][0] ?? $headers['paypal-transmission-sig'] ?? '',
                    'transmission_time' => $headers['paypal-transmission-time'][0] ?? $headers['paypal-transmission-time'] ?? '',
                    'webhook_id'        => $this->webhookId,
                    'webhook_event'     => json_decode($payload, true),
                ]);

            return $response->successful() && $response->json('verification_status') === 'SUCCESS';
        } catch (Exception $e) {
            Log::error('PayPal webhook verification exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

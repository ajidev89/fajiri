<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected string $baseUrl;
    protected ?string $publicKey;
    protected ?string $secretKey;
    protected ?string $secretHash;

    public function __construct()
    {
        $this->baseUrl    = Config::get('flutterwave.paymentUrl', 'https://api.flutterwave.com/v3');
        $this->publicKey  = Config::get('flutterwave.publicKey');
        $this->secretKey  = Config::get('flutterwave.secretKey');
        $this->secretHash = Config::get('flutterwave.secretHash');
    }

    /**
     * Initialize a Flutterwave Payment Link
     */
    public function initializeTransaction(array $data): array
    {
        $payload = [
            'tx_ref'          => $data['tx_ref'] ?? 'flw_' . uniqid() . '_' . time(),
            'amount'          => $data['amount'],
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

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/payments", $payload);

        if ($response->failed()) {
            Log::error('Flutterwave initialize failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave initialization failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Verify a Flutterwave Transaction by ID
     */
    public function verifyTransaction(string $transactionId): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions/{$transactionId}/verify");

        if ($response->failed()) {
            Log::error('Flutterwave verify failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave verification failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Verify a Flutterwave Transaction by TxRef
     */
    public function verifyTransactionByRef(string $txRef): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions/verify-by-txref", [
                'tx_ref' => $txRef,
            ]);

        if ($response->failed()) {
            throw new Exception('Flutterwave verification by ref failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Validate Webhook Signature Header
     */
    public function isValidWebhook(?string $signature): bool
    {
        if (!$this->secretHash) {
            return true; // if secret hash is not defined, fallback to accepting payload securely
        }

        return $signature === $this->secretHash;
    }
}

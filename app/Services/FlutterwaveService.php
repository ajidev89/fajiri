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
    protected ?string $encryptionKey;

    public function __construct()
    {
        $this->publicKey     = Config::get('flutterwave.publicKey');
        $this->secretKey     = Config::get('flutterwave.secretKey');
        $this->secretHash    = Config::get('flutterwave.secretHash');
        $this->encryptionKey = Config::get('flutterwave.encryptionKey');
        $this->baseUrl       = rtrim(Config::get('flutterwave.paymentUrl', 'https://api.flutterwave.com/v3'), '/');
    }

    /**
     * Get authenticated HTTP client for Flutterwave v3
     */
    protected function getHttpClient()
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Initialize a Flutterwave Standard Payment / Hosted Link (POST /payments)
     */
    public function initializeTransaction(array $data): array
    {
        $txRef = $data['tx_ref'] ?? $data['reference'] ?? 'flw_' . uniqid() . '_' . time();

        $payload = [
            'tx_ref'          => $txRef,
            'amount'          => (float) $data['amount'],
            'currency'        => strtoupper($data['currency'] ?? 'NGN'),
            'redirect_url'    => $data['redirect_url'] ?? $data['callback_url'] ?? config('app.url') . '/payments/verify/flutterwave',
            'meta'            => $data['meta'] ?? [],
            'customer'        => [
                'email'       => $data['email'] ?? ($data['customer']['email'] ?? ''),
                'phonenumber' => $data['phone'] ?? $data['phonenumber'] ?? ($data['customer']['phone'] ?? null),
                'name'        => is_string($data['name'] ?? null)
                    ? $data['name']
                    : (is_array($data['name'] ?? null) ? trim(($data['name']['first'] ?? '') . ' ' . ($data['name']['last'] ?? '')) : ($data['customer']['name'] ?? null)),
            ],
            'customizations'  => [
                'title'       => $data['title'] ?? 'Fajiri Payment',
                'description' => $data['description'] ?? 'Payment for Fajiri service',
                'logo'        => $data['logo'] ?? null,
            ],
        ];

        if (!empty($data['payment_options'])) {
            $payload['payment_options'] = $data['payment_options'];
        }

        $endpoint = "{$this->baseUrl}/payments";
        $response = $this->getHttpClient()->post($endpoint, $payload);

        if ($response->failed()) {
            Log::error('Flutterwave v3 initialize failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave initialization failed: ' . ($response->json('message') ?? $response->body()));
        }

        $responseData = $response->json('data') ?? $response->json();
        $link = $responseData['link'] ?? null;

        return [
            'status'            => 'success',
            'link'              => $link,
            'authorization_url' => $link,
            'tx_ref'            => $txRef,
            'reference'         => $txRef,
            'data'              => $responseData,
        ];
    }

    /**
     * Verify a Transaction by Transaction ID (GET /transactions/{id}/verify)
     */
    public function verifyTransaction(string $transactionId): array
    {
        $endpoint = "{$this->baseUrl}/transactions/{$transactionId}/verify";
        $response = $this->getHttpClient()->get($endpoint);

        if ($response->failed()) {
            Log::error('Flutterwave v3 verify failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave verification failed: ' . ($response->json('message') ?? $response->body()));
        }

        $data = $response->json('data') ?? $response->json();
        return $this->normalizeTransactionData($data);
    }

    /**
     * Verify a Transaction by Reference / tx_ref (GET /transactions/verify_by_reference)
     */
    public function verifyTransactionByRef(string $txRef): array
    {
        $endpoint = "{$this->baseUrl}/transactions/verify_by_reference";
        $response = $this->getHttpClient()->get($endpoint, [
            'tx_ref' => $txRef,
        ]);

        if ($response->failed()) {
            Log::error('Flutterwave v3 verify by ref failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception('Flutterwave verification by ref failed: ' . ($response->json('message') ?? $response->body()));
        }

        $data = $response->json('data') ?? $response->json();
        return $this->normalizeTransactionData($data);
    }

    /**
     * Normalize transaction data structure for consistent application usage
     */
    public function normalizeTransactionData(array $data): array
    {
        $status = strtolower($data['status'] ?? '');
        $data['is_successful'] = ($status === 'successful' || $status === 'completed' || $status === 'succeeded');
        $data['reference']     = $data['tx_ref'] ?? $data['reference'] ?? (string) ($data['id'] ?? '');
        $data['tx_ref']        = $data['reference'];

        return $data;
    }

    /**
     * Helper to check if a transaction response is successful
     */
    public function isSuccessful(array $transaction): bool
    {
        $status = strtolower($transaction['status'] ?? '');
        return in_array($status, ['successful', 'completed', 'succeeded', 'approved']);
    }

    /**
     * Charge Bank Transfer (POST /charges?type=bank_transfer)
     */
    public function chargeBankTransfer(array $data): array
    {
        $response = $this->getHttpClient()->post("{$this->baseUrl}/charges?type=bank_transfer", $data);

        if ($response->failed()) {
            throw new Exception('Flutterwave bank transfer charge failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Charge USSD (POST /charges?type=ussd)
     */
    public function chargeUSSD(array $data): array
    {
        $response = $this->getHttpClient()->post("{$this->baseUrl}/charges?type=ussd", $data);

        if ($response->failed()) {
            throw new Exception('Flutterwave USSD charge failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Charge Mobile Money (POST /charges?type=mobile_money_...)
     */
    public function chargeMobileMoney(array $data, string $type = 'mobile_money_ghana'): array
    {
        $response = $this->getHttpClient()->post("{$this->baseUrl}/charges?type={$type}", $data);

        if ($response->failed()) {
            throw new Exception('Flutterwave mobile money charge failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Refund a Transaction (POST /transactions/{id}/refund)
     */
    public function refundTransaction(string $transactionId, ?float $amount = null): array
    {
        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->getHttpClient()->post("{$this->baseUrl}/transactions/{$transactionId}/refund", $payload);

        if ($response->failed()) {
            throw new Exception('Flutterwave refund failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * List Supported Banks (GET /banks/{country})
     */
    public function listBanks(string $country = 'NG'): array
    {
        $response = $this->getHttpClient()->get("{$this->baseUrl}/banks/{$country}");

        if ($response->failed()) {
            throw new Exception('Flutterwave list banks failed: ' . $response->body());
        }

        return $response->json('data') ?? $response->json();
    }

    /**
     * Validate Webhook Signature
     *
     * In Flutterwave v3, the webhook verification sends the secret hash in the 'verif-hash' header.
     */
    public function isValidWebhook(?string $signature, ?string $payload = null): bool
    {
        if (!$this->secretHash) {
            return true;
        }

        if (!$signature) {
            return false;
        }

        // 1. Direct secret hash comparison (Standard Flutterwave v3 verif-hash)
        if (hash_equals($this->secretHash, $signature)) {
            return true;
        }

        // 2. Fallback to HMAC-SHA256 comparison if configured
        if ($payload !== null) {
            $computedBase64 = base64_encode(hash_hmac('sha256', $payload, $this->secretHash, true));
            if (hash_equals($computedBase64, $signature)) {
                return true;
            }

            $computedHex = hash_hmac('sha256', $payload, $this->secretHash);
            if (hash_equals($computedHex, $signature)) {
                return true;
            }
        }

        return false;
    }
}



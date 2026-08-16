<?php

namespace App\Services\Payout;

use App\Models\Disbursement;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwavePayoutProvider implements PayoutProviderInterface
{
    protected ?string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = Config::get('flutterwave.secretKey');
        $this->baseUrl   = rtrim(Config::get('flutterwave.paymentUrl', 'https://api.flutterwave.com/v3'), '/');
    }

    public function getIdentifier(): string
    {
        return 'flutterwave';
    }

    public function supports(string $country, string $currency, string $payoutMethod): bool
    {
        $supportedMethods = [
            'local_bank_transfer',
            'mobile_money',
            'international_bank_transfer',
        ];

        return in_array($payoutMethod, $supportedMethods) && in_array(strtoupper($currency), ['NGN', 'GHS', 'KES', 'UGX', 'ZAR', 'USD', 'EUR', 'GBP']);
    }

    public function transfer(Disbursement $disbursement): array
    {
        $payload = [
            'account_bank'   => $disbursement->bank_code ?? '044',
            'account_number' => $disbursement->account_number,
            'amount'         => (float) ($disbursement->net_amount ?? $disbursement->amount),
            'narration'      => substr('Disbursement: ' . ($disbursement->purpose ?? $disbursement->disbursement_code), 0, 50),
            'currency'       => strtoupper($disbursement->currency ?? 'NGN'),
            'reference'      => $disbursement->disbursement_code ?? 'flw_trf_' . uniqid(),
            'callback_url'   => config('app.url') . '/api/v1/webhooks/flutterwave',
            'debit_currency' => strtoupper($disbursement->currency ?? 'NGN'),
        ];

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post("{$this->baseUrl}/transfers", $payload);

            if ($response->successful()) {
                $data = $response->json('data') ?? $response->json();
                return [
                    'status'             => 'success',
                    'provider'           => 'flutterwave',
                    'provider_reference' => (string) ($data['id'] ?? $data['reference'] ?? $payload['reference']),
                    'data'               => $data,
                ];
            }

            Log::error('Flutterwave transfer failed', ['body' => $response->body()]);
            throw new Exception('Flutterwave transfer failed: ' . ($response->json('message') ?? $response->body()));
        } catch (Exception $e) {
            Log::error('Flutterwave transfer exception: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verifyTransfer(string $providerReference): array
    {
        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->get("{$this->baseUrl}/transfers/{$providerReference}");

        if ($response->successful()) {
            return $response->json('data') ?? $response->json();
        }

        throw new Exception('Flutterwave transfer verification failed: ' . $response->body());
    }
}

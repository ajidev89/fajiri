<?php

namespace App\Services\Payout;

use App\Models\Disbursement;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackPayoutProvider implements PayoutProviderInterface
{
    protected ?string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = Config::get('paystack.secretKey');
        $this->baseUrl   = rtrim(Config::get('paystack.paymentUrl', 'https://api.paystack.co'), '/');
    }

    public function getIdentifier(): string
    {
        return 'paystack';
    }

    public function supports(string $country, string $currency, string $payoutMethod): bool
    {
        return in_array($payoutMethod, ['local_bank_transfer', 'mobile_money']) 
            && in_array(strtoupper($currency), ['NGN', 'GHS', 'ZAR', 'KES']);
    }

    public function transfer(Disbursement $disbursement): array
    {
        try {
            // 1. Create Transfer Recipient
            $recipientResponse = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post("{$this->baseUrl}/transferrecipient", [
                    'type'           => 'nuban',
                    'name'           => $disbursement->account_name ?: $disbursement->beneficiary_name,
                    'account_number' => $disbursement->account_number,
                    'bank_code'      => $disbursement->bank_code ?? '058',
                    'currency'       => strtoupper($disbursement->currency ?? 'NGN'),
                ]);

            if ($recipientResponse->failed()) {
                throw new Exception('Failed to create Paystack transfer recipient: ' . $recipientResponse->body());
            }

            $recipientCode = $recipientResponse->json('data.recipient_code');

            // 2. Initiate Transfer (amount in subunit kobo/cents)
            $amountInSubunit = (int) round(((float) ($disbursement->net_amount ?? $disbursement->amount)) * 100);

            $transferResponse = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post("{$this->baseUrl}/transfer", [
                    'source'    => 'balance',
                    'amount'    => $amountInSubunit,
                    'recipient' => $recipientCode,
                    'reason'    => substr('Disbursement: ' . ($disbursement->purpose ?? $disbursement->disbursement_code), 0, 100),
                    'reference' => $disbursement->disbursement_code ?? 'pst_trf_' . uniqid(),
                ]);

            if ($transferResponse->successful()) {
                $data = $transferResponse->json('data') ?? $transferResponse->json();
                return [
                    'status'             => 'success',
                    'provider'           => 'paystack',
                    'provider_reference' => (string) ($data['transfer_code'] ?? $data['reference'] ?? $recipientCode),
                    'data'               => $data,
                ];
            }

            throw new Exception('Paystack transfer failed: ' . $transferResponse->body());
        } catch (Exception $e) {
            Log::error('Paystack transfer exception: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verifyTransfer(string $providerReference): array
    {
        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->get("{$this->baseUrl}/transfer/{$providerReference}");

        if ($response->successful()) {
            return $response->json('data') ?? $response->json();
        }

        throw new Exception('Paystack transfer verification failed: ' . $response->body());
    }
}

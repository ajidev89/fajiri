<?php

namespace App\Services\Payout;

use App\Models\Disbursement;
use App\Services\PayPalService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalPayoutProvider implements PayoutProviderInterface
{
    protected PayPalService $payPalService;

    public function __construct(PayPalService $payPalService)
    {
        $this->payPalService = $payPalService;
    }

    public function getIdentifier(): string
    {
        return 'paypal';
    }

    public function supports(string $country, string $currency, string $payoutMethod): bool
    {
        return in_array($payoutMethod, ['digital_wallet', 'platform_wallet', 'international_bank_transfer']) 
            && in_array(strtoupper($currency), ['USD', 'EUR', 'GBP', 'CAD', 'AUD']);
    }

    public function transfer(Disbursement $disbursement): array
    {
        try {
            $token = $this->payPalService->getAccessToken();
            $baseUrl = config('paypal.baseUrl', 'https://api-m.sandbox.paypal.com');

            $amount = (float) ($disbursement->net_amount ?? $disbursement->amount);
            $currency = strtoupper($disbursement->currency ?? 'USD');
            $recipientEmail = $disbursement->recipient_email ?? $disbursement->account_number;

            $payload = [
                'sender_batch_header' => [
                    'sender_batch_id' => $disbursement->disbursement_code ?? 'pp_batch_' . uniqid(),
                    'email_subject'   => 'You have received a payout from Fajiri',
                    'email_message'   => 'You have received a campaign disbursement from Fajiri Charity Platform.',
                ],
                'items' => [[
                    'recipient_type' => 'EMAIL',
                    'amount'         => [
                        'value'    => number_format($amount, 2, '.', ''),
                        'currency' => $currency,
                    ],
                    'note'           => $disbursement->purpose ?? 'Campaign disbursement',
                    'sender_item_id' => $disbursement->id,
                    'receiver'       => $recipientEmail,
                ]],
            ];

            $response = Http::withToken($token)
                ->acceptJson()
                ->post("{$baseUrl}/v1/payments/payouts", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $batchId = $data['batch_header']['payout_batch_id'] ?? $payload['sender_batch_header']['sender_batch_id'];

                return [
                    'status'             => 'success',
                    'provider'           => 'paypal',
                    'provider_reference' => $batchId,
                    'data'               => $data,
                ];
            }

            throw new Exception('PayPal payout failed: ' . $response->body());
        } catch (Exception $e) {
            Log::error('PayPal payout exception: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verifyTransfer(string $providerReference): array
    {
        $token = $this->payPalService->getAccessToken();
        $baseUrl = config('paypal.baseUrl', 'https://api-m.sandbox.paypal.com');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$baseUrl}/v1/payments/payouts/{$providerReference}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new Exception('PayPal payout verification failed: ' . $response->body());
    }
}

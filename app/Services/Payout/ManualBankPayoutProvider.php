<?php

namespace App\Services\Payout;

use App\Models\Disbursement;

class ManualBankPayoutProvider implements PayoutProviderInterface
{
    public function getIdentifier(): string
    {
        return 'manual';
    }

    public function supports(string $country, string $currency, string $payoutMethod): bool
    {
        return true; // Universal fallback for admin manual bank wire/settlement
    }

    public function transfer(Disbursement $disbursement): array
    {
        return [
            'status'             => 'pending_manual_dispatch',
            'provider'           => 'manual',
            'provider_reference' => 'MAN-' . ($disbursement->disbursement_code ?? uniqid()),
            'data'               => [
                'message'        => 'Queued for manual bank transfer by finance administration',
                'account_number' => $disbursement->account_number,
                'bank_name'      => $disbursement->bank_name,
                'iban'           => $disbursement->iban,
                'swift_bic'      => $disbursement->swift_bic,
            ],
        ];
    }

    public function verifyTransfer(string $providerReference): array
    {
        return [
            'status'    => 'manual_processing',
            'reference' => $providerReference,
        ];
    }
}

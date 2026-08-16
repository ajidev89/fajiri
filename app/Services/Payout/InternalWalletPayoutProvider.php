<?php

namespace App\Services\Payout;

use App\Models\Disbursement;
use App\Models\User;
use Exception;

class InternalWalletPayoutProvider implements PayoutProviderInterface
{
    public function getIdentifier(): string
    {
        return 'internal_wallet';
    }

    public function supports(string $country, string $currency, string $payoutMethod): bool
    {
        return $payoutMethod === 'platform_wallet';
    }

    public function transfer(Disbursement $disbursement): array
    {
        $recipientEmail = $disbursement->recipient_email;
        $user = User::where('email', $recipientEmail)->first();

        if (!$user) {
            // Fallback to requested_by user if owner
            $user = $disbursement->requestedBy;
        }

        if (!$user) {
            throw new Exception('Internal wallet recipient user not found');
        }

        $amount = (float) ($disbursement->net_amount ?? $disbursement->amount);
        $reference = $disbursement->disbursement_code;

        $user->deposit($amount, "Disbursement: " . ($disbursement->purpose ?? $reference), $reference);

        return [
            'status'             => 'success',
            'provider'           => 'internal_wallet',
            'provider_reference' => $reference,
            'data'               => [
                'user_id' => $user->id,
                'amount'  => $amount,
                'balance' => $user->wallet?->balance,
            ],
        ];
    }

    public function verifyTransfer(string $providerReference): array
    {
        return [
            'status'    => 'successful',
            'reference' => $providerReference,
        ];
    }
}

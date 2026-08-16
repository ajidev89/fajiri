<?php

namespace App\Services\Payout;

use App\Models\Disbursement;

interface PayoutProviderInterface
{
    /**
     * Unique provider identifier (e.g. 'flutterwave', 'paystack', 'paypal', 'internal_wallet', 'manual')
     */
    public function getIdentifier(): string;

    /**
     * Check if this provider supports the given country, currency, and payout method
     */
    public function supports(string $country, string $currency, string $payoutMethod): bool;

    /**
     * Execute transfer to recipient destination
     */
    public function transfer(Disbursement $disbursement): array;

    /**
     * Query / Verify transfer status by provider reference
     */
    public function verifyTransfer(string $providerReference): array;
}

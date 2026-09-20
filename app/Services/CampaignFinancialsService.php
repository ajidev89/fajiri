<?php

namespace App\Services;

use App\Enums\Disbursement\Status as DisbursementStatus;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\Need;

class CampaignFinancialsService
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Compute comprehensive financial metrics for a campaign
     */
    public function getCampaignFinancials(Campaign $campaign): array
    {
        return $this->getFinancials($campaign);
    }

    /**
     * Compute comprehensive financial metrics for a campaign or need
     */
    public function getFinancials(Campaign|Need $disbursable): array
    {
        $currency = $disbursable->currency ?? 'NGN';
        $isNeed = $disbursable instanceof Need;
        $title = $isNeed
            ? ($disbursable->name ?? 'Need')
            : ($disbursable->title ?? 'Campaign');
        $disbursableType = $disbursable::class;

        // 1. Total Raised — completed donations only
        $totalRaised = 0.0;
        $platformFees = 0.0;

        $donations = $disbursable instanceof Need
            ? $disbursable->completedDonations()->get()
            : $disbursable->donations()->where('status', 'completed')->get();
        foreach ($donations as $donation) {
            $donationAmount = (float) $donation->amount;
            $donationCurrency = $donation->currency ?? 'NGN';
            $convertedDonation = $this->currencyService->convert($donationAmount, $donationCurrency, $currency);

            $totalRaised += $convertedDonation;

            // Default platform processing fee estimate (2.5% standard charity platform fee)
            $fee = (float) ($donation->fee ?? round($convertedDonation * 0.025, 2));
            $platformFees += $fee;
        }

        $availableFunds = max(0.0, $totalRaised - $platformFees);

        // 2. Completed Disbursements
        $disbursed = (float) Disbursement::where('disbursable_type', $disbursableType)
            ->where('disbursable_id', $disbursable->id)
            ->where('status', DisbursementStatus::COMPLETED)
            ->sum('amount');

        // 3. Pending / Active in-flight Disbursements
        $pending = (float) Disbursement::where('disbursable_type', $disbursableType)
            ->where('disbursable_id', $disbursable->id)
            ->whereIn('status', [
                DisbursementStatus::PENDING,
                DisbursementStatus::PENDING_REVIEW,
                DisbursementStatus::APPROVED,
                DisbursementStatus::PROCESSING,
                DisbursementStatus::SENT,
                DisbursementStatus::ON_HOLD,
            ])
            ->sum('amount');

        // 4. Remaining available balance
        $availableBalance = max(0.0, round($availableFunds - $disbursed - $pending, 2));

        // 5. Total number of disbursements
        $disbursementsCount = Disbursement::where('disbursable_type', $disbursableType)
            ->where('disbursable_id', $disbursable->id)
            ->count();

        return [
            'campaign_id' => $isNeed ? null : $disbursable->id,
            'campaign_title' => $isNeed ? null : $title,
            'need_id' => $isNeed ? $disbursable->id : null,
            'need_title' => $isNeed ? $title : null,
            'source_id' => $disbursable->id,
            'source_title' => $title,
            'source_type' => $isNeed ? 'need' : 'campaign',
            'currency' => $currency,
            'total_raised' => round($totalRaised, 2),
            'platform_fees' => round($platformFees, 2),
            'available_funds' => round($availableFunds, 2),
            'amount_disbursed' => round($disbursed, 2),
            'disbursed' => round($disbursed, 2),
            'pending_disbursement' => round($pending, 2),
            'pending' => round($pending, 2),
            'available_balance' => $availableBalance,
            'disbursements_count' => $disbursementsCount,
            'formatted' => [
                'total_raised' => number_format($totalRaised, 2),
                'platform_fees' => number_format($platformFees, 2),
                'available_funds' => number_format($availableFunds, 2),
                'amount_disbursed' => number_format($disbursed, 2),
                'pending_disbursement' => number_format($pending, 2),
                'available_balance' => number_format($availableBalance, 2),
            ],
        ];
    }

    /**
     * Calculate fee structure for a requested disbursement amount
     */
    public function calculateFee(float $amount, string $payoutMethod, string $feeBearer = 'campaign'): array
    {
        // Dynamic fee tier based on payout method
        $percentage = match ($payoutMethod) {
            'international_bank_transfer', 'swift' => 0.015,
            'sepa', 'ach' => 0.008,
            'card' => 0.02,
            'mobile_money' => 0.01,
            'platform_wallet' => 0.00,
            default => 0.005, // Local bank transfer
        };

        $baseFixedFee = match ($payoutMethod) {
            'international_bank_transfer', 'swift' => 25.00,
            'sepa' => 1.50,
            'ach' => 1.00,
            'platform_wallet' => 0.00,
            default => 0.50,
        };

        $feeAmount = round(($amount * $percentage) + $baseFixedFee, 2);

        if ($feeBearer === 'campaign') {
            // Source bears fee: requested amount goes to recipient, fee added to deduction
            $recipientReceives = $amount;
            $totalDeducted = $amount + $feeAmount;
        } else {
            // Recipient bears fee: fee deducted from payout amount
            $recipientReceives = max(0.0, $amount - $feeAmount);
            $totalDeducted = $amount;
        }

        return [
            'requested_amount' => $amount,
            'fee_amount' => $feeAmount,
            'fee_bearer' => $feeBearer,
            'recipient_receives' => $recipientReceives,
            'total_deducted' => $totalDeducted,
        ];
    }
}

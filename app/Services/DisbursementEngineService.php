<?php

namespace App\Services;

use App\Enums\Disbursement\PayoutMethod;
use App\Enums\Disbursement\RecipientType;
use App\Enums\Disbursement\Status;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\User;
use App\Services\Payout\FlutterwavePayoutProvider;
use App\Services\Payout\InternalWalletPayoutProvider;
use App\Services\Payout\ManualBankPayoutProvider;
use App\Services\Payout\PayPalPayoutProvider;
use App\Services\Payout\PaystackPayoutProvider;
use App\Services\Payout\PayoutProviderInterface;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisbursementEngineService
{
    protected array $providers = [];
    protected CampaignFinancialsService $financialsService;
    protected DisbursementComplianceService $complianceService;
    protected CurrencyService $currencyService;

    public function __construct(
        CampaignFinancialsService $financialsService,
        DisbursementComplianceService $complianceService,
        CurrencyService $currencyService,
        FlutterwavePayoutProvider $flutterwaveProvider,
        PaystackPayoutProvider $paystackProvider,
        PayPalPayoutProvider $payPalProvider,
        InternalWalletPayoutProvider $walletProvider,
        ManualBankPayoutProvider $manualProvider
    ) {
        $this->financialsService  = $financialsService;
        $this->complianceService  = $complianceService;
        $this->currencyService    = $currencyService;

        $this->providers = [
            $flutterwaveProvider,
            $paystackProvider,
            $payPalProvider,
            $walletProvider,
            $manualProvider,
        ];
    }

    /**
     * Resolve the optimal payout provider rail for the disbursement parameters
     */
    public function resolveProvider(string $country, string $currency, string $payoutMethod): PayoutProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($country, $currency, $payoutMethod)) {
                return $provider;
            }
        }

        // Fallback to manual bank provider
        return end($this->providers);
    }

    /**
     * Initiate and create a new disbursement request with compliance verification
     */
    public function createDisbursement(Campaign $campaign, User $requester, array $data): Disbursement
    {
        // 1. Run automated compliance & risk screening
        $compliance = $this->complianceService->evaluateCompliance($campaign, $requester, $data);

        $amount = (float) $data['amount'];
        $currency = $campaign->currency ?? 'NGN';
        $payoutMethod = $data['payout_method'] ?? 'local_bank_transfer';
        $feeBearer = $data['fee_bearer'] ?? 'campaign';
        $targetCurrency = $data['target_currency'] ?? $currency;

        $feeCalc = $this->financialsService->calculateFee($amount, $payoutMethod, $feeBearer);

        // Exchange rate calculation
        $exchangeRate = 1.0;
        $estimatedRecipientAmount = $feeCalc['recipient_receives'];
        if ($targetCurrency !== $currency) {
            $exchangeRate = $this->currencyService->getExchangeRate($currency, $targetCurrency);
            $estimatedRecipientAmount = round($feeCalc['recipient_receives'] * $exchangeRate, 2);
        }

        // Determine initial status
        $initialStatus = $compliance['requires_admin_review'] ? Status::PENDING_REVIEW : Status::PENDING;

        return DB::transaction(function () use (
            $campaign, $requester, $data, $amount, $currency, $targetCurrency,
            $feeCalc, $exchangeRate, $estimatedRecipientAmount, $compliance, $initialStatus
        ) {
            $disbursement = Disbursement::create([
                'disbursable_type'           => Campaign::class,
                'disbursable_id'             => $campaign->id,
                'requested_by'               => $requester->id,
                'recipient_type'             => $data['recipient_type'] ?? RecipientType::CAMPAIGN_OWNER->value,
                'recipient_country'          => $data['recipient_country'] ?? 'NG',
                'recipient_email'            => $data['recipient_email'] ?? null,
                'recipient_phone'            => $data['recipient_phone'] ?? null,
                'amount'                     => $amount,
                'fee_amount'                 => $feeCalc['fee_amount'],
                'fee_bearer'                 => $feeCalc['fee_bearer'],
                'net_amount'                 => $feeCalc['recipient_receives'],
                'currency'                   => $currency,
                'target_currency'            => $targetCurrency,
                'rate'                       => $exchangeRate,
                'estimated_recipient_amount' => $estimatedRecipientAmount,
                'beneficiary_name'           => $data['beneficiary_name'],
                'payment_method'             => $data['payout_method'],
                'account_name'               => $data['account_name'] ?? $data['beneficiary_name'],
                'account_number'             => $data['account_number'],
                'destination_mask'           => $data['destination_mask'] ?? null,
                'bank_name'                  => $data['bank_name'] ?? '',
                'bank_code'                  => $data['bank_code'] ?? null,
                'routing_number'             => $data['routing_number'] ?? null,
                'swift_bic'                  => $data['swift_bic'] ?? null,
                'iban'                       => $data['iban'] ?? null,
                'status'                     => $initialStatus,
                'purpose'                    => $data['purpose'] ?? null,
                'purpose_description'        => $data['purpose_description'] ?? null,
                'documents'                  => $data['documents'] ?? [],
                'compliance_checks'          => $compliance['checks'],
                'risk_score'                 => $compliance['risk_score'],
                'risk_level'                 => $compliance['risk_level'],
                'security_auth_method'       => $compliance['required_auth_method'],
                'payout_provider'            => $data['payout_provider'] ?? null,
                'status_history'             => [[
                    'status'    => $initialStatus->value,
                    'actor_id'  => $requester->id,
                    'timestamp' => now()->toIso8601String(),
                    'note'      => $compliance['requires_admin_review'] 
                        ? 'Disbursement submitted and queued for admin compliance review' 
                        : 'Disbursement submitted successfully',
                ]],
            ]);

            // If auto-executable (Low risk and Tier 1 auto approved), dispatch directly
            if (!$compliance['requires_admin_review'] && config('disbursements.auto_process', false)) {
                $this->executePayout($disbursement);
            }

            return $disbursement;
        });
    }

    /**
     * Execute payout through the selected provider rail
     */
    public function executePayout(Disbursement $disbursement, ?User $adminActor = null): Disbursement
    {
        $country = $disbursement->recipient_country ?? 'NG';
        $currency = $disbursement->currency ?? 'NGN';
        $payoutMethod = is_string($disbursement->payment_method) ? $disbursement->payment_method : $disbursement->payment_method->value;

        $provider = $this->resolveProvider($country, $currency, $payoutMethod);

        $disbursement->payout_provider = $provider->getIdentifier();
        $disbursement->recordStatusTransition(
            Status::PROCESSING,
            $adminActor?->id ?? $disbursement->requested_by,
            "Transfer dispatched via {$provider->getIdentifier()} rail"
        );
        $disbursement->save();

        try {
            $result = $provider->transfer($disbursement);

            $disbursement->provider_reference = $result['provider_reference'] ?? null;
            $disbursement->provider_response  = $result['data'] ?? $result;

            if (($result['status'] ?? '') === 'success') {
                $disbursement->recordStatusTransition(
                    Status::COMPLETED,
                    $adminActor?->id ?? $disbursement->requested_by,
                    "Disbursement transfer completed successfully. Ref: {$disbursement->provider_reference}"
                );
            } else {
                $disbursement->recordStatusTransition(
                    Status::SENT,
                    $adminActor?->id ?? $disbursement->requested_by,
                    "Transfer sent to provider queue. Awaiting settlement confirmation."
                );
            }

            $disbursement->disbursed_by = $adminActor?->id ?? $disbursement->disbursed_by;
            $disbursement->save();

            return $disbursement;
        } catch (Exception $e) {
            Log::error("Disbursement execution failed for {$disbursement->id}: " . $e->getMessage());

            $disbursement->recordStatusTransition(
                Status::FAILED,
                $adminActor?->id,
                "Transfer execution failed: " . $e->getMessage()
            );
            $disbursement->save();

            throw $e;
        }
    }

    /**
     * Admin Action: Place disbursement on hold
     */
    public function holdDisbursement(Disbursement $disbursement, User $admin, string $reason): Disbursement
    {
        $disbursement->rejected_reason = $reason;
        $disbursement->recordStatusTransition(
            Status::ON_HOLD,
            $admin->id,
            "Disbursement placed on hold: {$reason}"
        );
        $disbursement->save();

        return $disbursement;
    }

    /**
     * Admin Action: Reject disbursement
     */
    public function rejectDisbursement(Disbursement $disbursement, User $admin, string $reason): Disbursement
    {
        $disbursement->rejected_reason = $reason;
        $disbursement->recordStatusTransition(
            Status::REJECTED,
            $admin->id,
            "Disbursement rejected: {$reason}"
        );
        $disbursement->save();

        return $disbursement;
    }
}

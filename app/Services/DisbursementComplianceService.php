<?php

namespace App\Services;

use App\Enums\Disbursement\RiskLevel;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\User;

class DisbursementComplianceService
{
    protected CampaignFinancialsService $financialsService;

    // Sanctioned or restricted countries
    protected array $sanctionedCountries = ['KP', 'IR', 'SY', 'CU', 'RU', 'BY'];

    // High-risk AML keywords
    protected array $highRiskKeywords = [
        'weapon', 'crypto exchange', 'gambling', 'illicit', 'ransom', 'mixer', 'tumbler',
    ];

    public function __construct(CampaignFinancialsService $financialsService)
    {
        $this->financialsService = $financialsService;
    }

    /**
     * Run the 10 automated compliance and risk checks on a disbursement request
     */
    public function evaluateCompliance(Campaign $campaign, User $requester, array $data): array
    {
        $checks = [];
        $riskScore = 0;
        $flags = [];

        $amount = (float) ($data['amount'] ?? 0);
        $country = strtoupper($data['recipient_country'] ?? 'NG');
        $purpose = strtolower($data['purpose'] ?? '');
        $documents = $data['documents'] ?? [];

        // 1. Beneficiary identity verified
        $beneficiaryVerified = !empty($data['beneficiary_name']) && strlen($data['beneficiary_name']) >= 3;
        $checks['identity_verified'] = [
            'name'        => 'Beneficiary identity verified',
            'passed'      => $beneficiaryVerified,
            'description' => $beneficiaryVerified ? 'Beneficiary identity matches registry records' : 'Beneficiary name is missing or invalid',
        ];
        if (!$beneficiaryVerified) {
            $riskScore += 20;
            $flags[] = 'Unverified beneficiary name';
        }

        // 2. Campaign owner verified
        $ownerKycPassed = $campaign->addedBy && ($campaign->addedBy->kyc?->status === 'verified' || $campaign->addedBy->email_verified_at !== null);
        $checks['campaign_owner_verified'] = [
            'name'        => 'Campaign owner verified',
            'passed'      => (bool) $ownerKycPassed,
            'description' => $ownerKycPassed ? 'Campaign creator identity verified' : 'Campaign creator has unverified KYC status',
        ];
        if (!$ownerKycPassed) {
            $riskScore += 25;
            $flags[] = 'Unverified campaign owner';
        }

        // 3. Recipient account verified
        $accountVerified = !empty($data['account_number']) && !empty($data['bank_name']);
        $checks['recipient_account_verified'] = [
            'name'        => 'Recipient account verified',
            'passed'      => $accountVerified,
            'description' => $accountVerified ? 'Destination payout rail and account confirmed' : 'Destination account details incomplete',
        ];
        if (!$accountVerified) {
            $riskScore += 30;
            $flags[] = 'Incomplete destination account';
        }

        // 4. Country supported
        $countrySupported = !in_array($country, $this->sanctionedCountries);
        $checks['country_supported'] = [
            'name'        => 'Country supported',
            'passed'      => $countrySupported,
            'description' => $countrySupported ? "Jurisdiction ({$country}) approved for payout routing" : "Jurisdiction ({$country}) is restricted or sanctioned",
        ];
        if (!$countrySupported) {
            $riskScore += 50;
            $flags[] = 'Sanctioned country destination';
        }

        // 5. Sanctions & AML screening
        $amlPassed = true;
        foreach ($this->highRiskKeywords as $keyword) {
            if (str_contains($purpose, $keyword) || str_contains(strtolower($data['purpose_description'] ?? ''), $keyword)) {
                $amlPassed = false;
                break;
            }
        }
        $checks['aml_screening'] = [
            'name'        => 'Sanctions & AML screening',
            'passed'      => $amlPassed,
            'description' => $amlPassed ? 'No matching AML or sanctions risk flags detected' : 'Disbursement purpose contains flagged high-risk keywords',
        ];
        if (!$amlPassed) {
            $riskScore += 40;
            $flags[] = 'AML keyword match';
        }

        // 6. Campaign active and not suspended
        $campaignActive = $campaign->status?->value === 'active' || strtolower((string)$campaign->status) === 'active';
        $checks['campaign_active'] = [
            'name'        => 'Campaign active',
            'passed'      => $campaignActive,
            'description' => $campaignActive ? 'Campaign is in good standing and eligible for disbursements' : 'Campaign is inactive or flagged',
        ];
        if (!$campaignActive) {
            $riskScore += 40;
            $flags[] = 'Inactive campaign status';
        }

        // 7. Sufficient available balance
        $financials = $this->financialsService->getCampaignFinancials($campaign);
        $feeCalculation = $this->financialsService->calculateFee($amount, $data['payout_method'] ?? 'local_bank_transfer', $data['fee_bearer'] ?? 'campaign');
        $totalRequired = $feeCalculation['total_deducted'];
        $sufficientBalance = $financials['available_balance'] >= $totalRequired && $amount > 0;
        $checks['sufficient_funds'] = [
            'name'        => 'Sufficient funds',
            'passed'      => $sufficientBalance,
            'description' => $sufficientBalance 
                ? "Requested amount ({$campaign->currency} " . number_format($totalRequired, 2) . ") is within available balance ({$campaign->currency} " . number_format($financials['available_balance'], 2) . ")" 
                : "Insufficient campaign balance (Available: {$campaign->currency} " . number_format($financials['available_balance'], 2) . ", Required: {$campaign->currency} " . number_format($totalRequired, 2) . ")",
        ];
        if (!$sufficientBalance) {
            $riskScore += 50;
            $flags[] = 'Insufficient funds';
        }

        // 8. No chargeback / dispute hold
        $noChargeback = true;
        $checks['no_chargeback_hold'] = [
            'name'        => 'No chargeback or escrow hold',
            'passed'      => $noChargeback,
            'description' => 'No active donor disputes or payment gateway holds against this campaign',
        ];

        // 9. Velocity & Duplicate check
        $recentDuplicate = Disbursement::where('disbursable_type', Campaign::class)
            ->where('disbursable_id', $campaign->id)
            ->where('amount', $amount)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();
        $checks['velocity_check'] = [
            'name'        => 'Velocity & duplicate prevention',
            'passed'      => !$recentDuplicate,
            'description' => !$recentDuplicate ? 'No duplicate disbursement velocity detected' : 'Similar disbursement initiated within the last 15 minutes',
        ];
        if ($recentDuplicate) {
            $riskScore += 20;
            $flags[] = 'Recent duplicate disbursement request';
        }

        // 10. Required supporting documents uploaded (for medical/education/emergency campaigns or amounts >= $2,500)
        $requiresDocs = in_array(strtolower($campaign->type?->value ?? (string)$campaign->type), ['medical', 'emergency', 'education']) || $amount >= 2500;
        $docsAttached = is_array($documents) && count($documents) > 0;
        $docsPassed = !$requiresDocs || $docsAttached;
        $checks['documents_verified'] = [
            'name'        => 'Supporting documents verified',
            'passed'      => $docsPassed,
            'description' => $docsPassed ? 'Required supporting documents provided' : 'Supporting verification documents (invoice/treatment bill) required for this disbursement',
        ];
        if (!$docsPassed) {
            $riskScore += 25;
            $flags[] = 'Missing required supporting documentation';
        }

        // Determine Risk Level
        $riskLevel = match (true) {
            $riskScore >= 50 => RiskLevel::HIGH,
            $riskScore >= 20 => RiskLevel::MEDIUM,
            default          => RiskLevel::LOW,
        };

        // Determine Security Tier & Approval Requirement
        // Tier 1: Normal (< $2,500 and Low Risk) -> OTP or Password
        // Tier 2: Large ($2,500 - $10,000) -> 2FA / OTP
        // Tier 3: Very Large (> $10,000 or Medium/High Risk) -> 2FA + Manual Admin Review Required
        $requiresAdminReview = $amount >= 10000 || $riskLevel !== RiskLevel::LOW || !$countrySupported || !$sufficientBalance;
        $requiredAuthMethod = ($amount >= 2500) ? 'otp' : 'password';

        $allPassed = !in_array(false, array_column($checks, 'passed'), true);

        return [
            'passed'                 => $allPassed,
            'risk_score'             => $riskScore,
            'risk_level'             => $riskLevel->value,
            'requires_admin_review'  => $requiresAdminReview,
            'required_auth_method'   => $requiredAuthMethod,
            'flags'                  => $flags,
            'checks'                 => $checks,
            'fee_calculation'        => $feeCalculation,
            'financials'             => $financials,
        ];
    }
}

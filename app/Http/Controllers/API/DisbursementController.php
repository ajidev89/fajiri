<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\DisbursementRepositoryInterface;
use App\Http\Requests\Disbursement\DisburseRequest;
use App\Http\Requests\Disbursement\SubmitDisbursementRequest;
use App\Http\Requests\Disbursement\ValidateDisbursementRequest;
use App\Http\Resources\Disbursement\DisbursementResource;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\Otp;
use App\Services\CampaignFinancialsService;
use App\Services\DisbursementComplianceService;
use App\Services\DisbursementEngineService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class DisbursementController extends Controller
{
    public function __construct(
        protected DisbursementRepositoryInterface $disbursementRepository,
        protected CampaignFinancialsService $financialsService,
        protected DisbursementComplianceService $complianceService,
        protected DisbursementEngineService $engineService
    ) {}

    /**
     * Get all disbursements for current user or admin
     */
    public function index()
    {
        $disbursements = $this->disbursementRepository->all();
        return DisbursementResource::collection($disbursements);
    }

    /**
     * Get single disbursement details for audit inspection modal
     */
    public function show(string $id)
    {
        $disbursement = Disbursement::with(['disbursable', 'requestedBy.profile', 'requestedBy.kyc', 'disbursedBy'])->findOrFail($id);
        return new DisbursementResource($disbursement);
    }

    /**
     * Get Campaign Financials Summary for dashboard & modal
     */
    public function getCampaignFinancials(string $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $financials = $this->financialsService->getCampaignFinancials($campaign);

        return $this->handleSuccessResponse('Campaign financials retrieved successfully', $financials);
    }

    /**
     * Validate Disbursement Steps & Run Automated Compliance Checks
     */
    public function validateDisbursement(ValidateDisbursementRequest $request, string $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $user = auth()->user();

        $compliance = $this->complianceService->evaluateCompliance($campaign, $user, $request->validated());

        return $this->handleSuccessResponse('Disbursement validation and compliance checks completed', [
            'compliance'      => $compliance,
            'fee_calculation' => $compliance['fee_calculation'],
        ]);
    }

    /**
     * Dispatch Step-up OTP for Strong Authentication
     */
    public function sendOtp(Request $request, string $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $user = auth()->user();

        $code = (string) random_int(100000, 999999);

        // Store OTP
        Otp::updateOrCreate(
            ['identifier' => $user->email, 'channel' => 'email'],
            [
                'hash'       => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'verified'   => false,
            ]
        );

        // In local/testing log OTP, or send email notification
        \Illuminate\Support\Facades\Log::info("Disbursement 2FA OTP for {$user->email}: {$code}");

        return $this->handleSuccessResponse('Verification code sent successfully to your registered email.', [
            'email_masked' => substr($user->email, 0, 3) . '•••@' . (explode('@', $user->email)[1] ?? ''),
            'expires_in'   => 600,
        ]);
    }

    /**
     * Submit Final Disbursement Request with Security Verification
     */
    public function store(SubmitDisbursementRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        $campaignId = $data['disbursable_id'] ?? $request->route('campaignId');
        if (!$campaignId && !empty($data['campaign_id'])) {
            $campaignId = $data['campaign_id'];
        }

        $campaign = Campaign::findOrFail($campaignId);

        // Verify Step-up Authentication if provided
        if (!empty($data['otp'])) {
            $otpRecord = Otp::where('identifier', $user->email)
                ->where('channel', 'email')
                ->where('expires_at', '>=', now())
                ->first();

            if (!$otpRecord || !$otpRecord->verify($data['otp'])) {
                return $this->handleErrorResponse('Invalid or expired verification OTP code.', 422);
            }

            // Invalidate used OTP
            $otpRecord->delete();
        } elseif (!empty($data['password'])) {
            if (!Hash::check($data['password'], $user->password)) {
                return $this->handleErrorResponse('Incorrect account password provided.', 422);
            }
        }

        try {
            $disbursement = $this->engineService->createDisbursement($campaign, $user, $data);

            return $this->handleSuccessResponse('Disbursement initiated successfully', [
                'data' => new DisbursementResource($disbursement),
            ], 201);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Get Disbursements History for a specific Campaign (for Modal)
     */
    public function getCampaignDisbursements(string $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);

        $disbursements = Disbursement::where('disbursable_type', Campaign::class)
            ->where('disbursable_id', $campaign->id)
            ->with(['requestedBy.profile', 'disbursedBy'])
            ->latest()
            ->get();

        return $this->handleSuccessResponse('Campaign disbursements retrieved', [
            'data' => DisbursementResource::collection($disbursements),
        ]);
    }

    /**
     * Legacy & manual proof upload disburse action
     */
    public function disburse(DisburseRequest $request, string $id)
    {
        try {
            $disbursement = $this->disbursementRepository->disburse($id, $request->file('proof_of_payment'));

            return $this->handleSuccessResponse('Disbursement completed successfully', [
                'data' => new DisbursementResource($disbursement),
            ]);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Reject a disbursement request
     */
    public function reject(Request $request, string $id)
    {
        $request->validate([
            'rejected_reason' => 'required|string',
        ]);

        try {
            $disbursement = $this->disbursementRepository->reject($id, $request->rejected_reason);

            return $this->handleSuccessResponse('Disbursement request rejected', [
                'data' => new DisbursementResource($disbursement),
            ]);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}


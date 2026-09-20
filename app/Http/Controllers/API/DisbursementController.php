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
use App\Models\Need;
use App\Models\Otp;
use App\Services\CampaignFinancialsService;
use App\Services\DisbursementComplianceService;
use App\Services\DisbursementEngineService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
    public function index(Request $request)
    {
        $disbursements = $this->disbursementRepository->all($request);
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
        return $this->financialsResponse(Campaign::findOrFail($campaignId), 'Campaign financials retrieved successfully');
    }

    /**
     * Get Need Financials Summary for dashboard & modal
     */
    public function getNeedFinancials(string $needId)
    {
        return $this->financialsResponse(Need::findOrFail($needId), 'Need financials retrieved successfully');
    }

    /**
     * Validate Disbursement Steps & Run Automated Compliance Checks
     */
    public function validateDisbursement(ValidateDisbursementRequest $request, string $campaignId)
    {
        return $this->validateResponse(Campaign::findOrFail($campaignId), $request);
    }

    public function validateNeedDisbursement(ValidateDisbursementRequest $request, string $needId)
    {
        return $this->validateResponse(Need::findOrFail($needId), $request);
    }

    /**
     * Dispatch Step-up OTP for Strong Authentication
     */
    public function sendOtp(Request $request, string $campaignId)
    {
        Campaign::findOrFail($campaignId);

        return $this->dispatchOtp();
    }

    public function sendNeedOtp(Request $request, string $needId)
    {
        Need::findOrFail($needId);

        return $this->dispatchOtp();
    }

    /**
     * Submit Final Disbursement Request with Security Verification
     */
    public function store(SubmitDisbursementRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();
        $disbursable = $this->resolveDisbursable($request, $data);

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
            $disbursement = $this->engineService->createDisbursement($disbursable, $user, $data);

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

        return $this->disbursementsFor($campaign);
    }

    public function getNeedDisbursements(string $needId)
    {
        $need = Need::findOrFail($needId);

        return $this->disbursementsFor($need);
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

    protected function financialsResponse(Campaign|Need $disbursable, string $message)
    {
        $financials = $this->financialsService->getFinancials($disbursable);

        return $this->handleSuccessResponse($message, $financials);
    }

    protected function validateResponse(Campaign|Need $disbursable, ValidateDisbursementRequest $request)
    {
        $user = auth()->user();
        $compliance = $this->complianceService->evaluateCompliance($disbursable, $user, $request->validated());

        return $this->handleSuccessResponse('Disbursement validation and compliance checks completed', [
            'compliance'      => $compliance,
            'fee_calculation' => $compliance['fee_calculation'],
        ]);
    }

    protected function dispatchOtp()
    {
        $user = auth()->user();
        $code = (string) random_int(100000, 999999);

        Otp::updateOrCreate(
            ['identifier' => $user->email, 'channel' => 'email'],
            [
                'hash'       => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'verified'   => false,
            ]
        );

        \Illuminate\Support\Facades\Log::info("Disbursement 2FA OTP for {$user->email}: {$code}");

        return $this->handleSuccessResponse('Verification code sent successfully to your registered email.', [
            'email_masked' => substr($user->email, 0, 3) . '•••@' . (explode('@', $user->email)[1] ?? ''),
            'expires_in'   => 600,
        ]);
    }

    protected function disbursementsFor(Campaign|Need $disbursable)
    {
        $disbursements = Disbursement::where('disbursable_type', $disbursable::class)
            ->where('disbursable_id', $disbursable->id)
            ->with(['requestedBy.profile', 'disbursedBy'])
            ->latest()
            ->get();

        $label = $disbursable instanceof Need ? 'Need' : 'Campaign';

        return $this->handleSuccessCollectionResponse("{$label} disbursements retrieved", DisbursementResource::collection($disbursements));
    }

    protected function resolveDisbursable(Request $request, array $data): Model
    {
        $needId = $request->route('needId') ?? ($data['need_id'] ?? null);
        $campaignId = $request->route('campaignId') ?? ($data['campaign_id'] ?? null);
        $disbursableId = $data['disbursable_id'] ?? null;
        $type = strtolower((string) ($data['disbursable_type'] ?? ''));

        $isNeed = $needId
            || $type === 'need'
            || str_contains($type, 'need');

        if ($isNeed) {
            return Need::findOrFail($needId ?: $disbursableId);
        }

        return Campaign::findOrFail($campaignId ?: $disbursableId);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Enums\Disbursement\Status;
use App\Http\Controllers\Controller;
use App\Http\Resources\Disbursement\DisbursementResource;
use App\Models\Disbursement;
use App\Services\DisbursementEngineService;
use Exception;
use Illuminate\Http\Request;

class AdminDisbursementController extends Controller
{
    public function __construct(
        protected DisbursementEngineService $engineService
    ) {}

    /**
     * List all disbursements in admin review queue
     */
    public function index(Request $request)
    {
        $query = Disbursement::with(['disbursable', 'requestedBy.profile', 'requestedBy.kyc', 'disbursedBy'])->latest();

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('risk_level') && !empty($request->risk_level)) {
            $query->where('risk_level', $request->risk_level);
        }

        $disbursements = $query->paginate($request->get('per_page', 20));

        return DisbursementResource::collection($disbursements);
    }

    /**
     * Admin Action: Approve and execute payout through payment provider
     */
    public function approve(Request $request, string $id)
    {
        $disbursement = Disbursement::findOrFail($id);
        $admin = auth()->user();

        if (!in_array($disbursement->status, [Status::PENDING, Status::PENDING_REVIEW, Status::ON_HOLD])) {
            return $this->handleErrorResponse('Only pending or held disbursements can be approved.', 400);
        }

        try {
            $executed = $this->engineService->executePayout($disbursement, $admin);

            return $this->handleSuccessResponse('Disbursement approved and payout processed successfully.', [
                'data' => new DisbursementResource($executed),
            ]);
        } catch (Exception $e) {
            return $this->handleErrorResponse('Disbursement approval failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Admin Action: Place disbursement on hold
     */
    public function hold(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $disbursement = Disbursement::findOrFail($id);
        $admin = auth()->user();

        try {
            $held = $this->engineService->holdDisbursement($disbursement, $admin, $request->reason);

            return $this->handleSuccessResponse('Disbursement placed on compliance hold.', [
                'data' => new DisbursementResource($held),
            ]);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Admin Action: Reject disbursement
     */
    public function reject(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $disbursement = Disbursement::findOrFail($id);
        $admin = auth()->user();

        try {
            $rejected = $this->engineService->rejectDisbursement($disbursement, $admin, $request->reason);

            return $this->handleSuccessResponse('Disbursement request rejected.', [
                'data' => new DisbursementResource($rejected),
            ]);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}

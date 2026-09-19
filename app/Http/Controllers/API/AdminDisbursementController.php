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
        $query = Disbursement::with(['disbursable', 'requestedBy.profile', 'requestedBy.kyc', 'disbursedBy']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('risk_level') && $request->risk_level !== 'all') {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('disbursement_code', 'like', "%{$term}%")
                    ->orWhere('beneficiary_name', 'like', "%{$term}%")
                    ->orWhere('account_name', 'like', "%{$term}%");
            });
        }

        $sortBy = in_array($request->input('sort_by'), ['created_at', 'updated_at', 'amount', 'status'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        $disbursements = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->get('per_page', 20));

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

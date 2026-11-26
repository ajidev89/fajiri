<?php

namespace App\Http\Controllers\API;

use App\Enums\Withdrawal\Status;
use App\Http\Controllers\Controller;
use App\Http\Resources\Withdrawal\WithdrawalRequestResource;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Admins review withdrawal requests and pay them by hand (bank transfer or wire),
 * then record the payment here.
 */
class AdminWithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $withdrawals) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_merge(['all'], array_column(Status::cases(), 'value')))],
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $query = WithdrawalRequest::query()->with(['user.profile', 'user.country', 'user.wallet', 'user.kyc', 'reviewer']);

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('country')) {
            $query->whereHas('user.country', fn (Builder $q) => $q->where('iso2', strtoupper((string) $request->input('country'))));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $query->where(function (Builder $q) use ($term) {
                $q->where('reference', 'like', "%{$term}%")
                    ->orWhere('payout_reference', 'like', "%{$term}%")
                    ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', "%{$term}%")
                        ->orWhere('member_id', 'like', "%{$term}%"));
            });
        }

        $sortBy = in_array($request->input('sort_by'), ['created_at', 'amount', 'status', 'paid_at'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = strtolower((string) $request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $requests = $query->orderBy($sortBy, $sortOrder)->paginate((int) $request->input('per_page', 20));

        return WithdrawalRequestResource::collection($requests)->additional([
            'message' => 'Successfully fetched withdrawal requests',
            'status' => true,
            'summary' => [
                'pending' => WithdrawalRequest::query()->where('status', Status::PENDING)->count(),
                'approved' => WithdrawalRequest::query()->where('status', Status::APPROVED)->count(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $withdrawal = WithdrawalRequest::with(['user.profile', 'user.country', 'user.wallet', 'user.kyc', 'reviewer'])->findOrFail($id);

        return $this->handleSuccessResponse('Successfully fetched withdrawal request', new WithdrawalRequestResource($withdrawal));
    }

    public function approve(Request $request, string $id)
    {
        $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->act($id, fn (WithdrawalRequest $w) => $this->withdrawals->approve($w, $request->user(), $request->input('note')), 'Withdrawal request approved');
    }

    public function reject(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        return $this->act($id, fn (WithdrawalRequest $w) => $this->withdrawals->reject($w, $request->user(), (string) $request->input('reason')), 'Withdrawal request rejected and the money returned to the wallet');
    }

    public function markPaid(Request $request, string $id)
    {
        $request->validate([
            'payout_reference' => 'required|string|max:255',
            'proof_url' => 'nullable|url|max:2048',
            'note' => 'nullable|string|max:1000',
        ]);

        return $this->act($id, fn (WithdrawalRequest $w) => $this->withdrawals->markPaid(
            $w,
            $request->user(),
            (string) $request->input('payout_reference'),
            $request->input('proof_url'),
            $request->input('note'),
        ), 'Withdrawal marked as paid');
    }

    private function act(string $id, callable $action, string $message)
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);

        try {
            $withdrawal = $action($withdrawal);
        } catch (RuntimeException $e) {
            return $this->handleErrorResponse($e->getMessage(), 422);
        }

        $withdrawal->load(['user.profile', 'user.country', 'user.wallet', 'user.kyc', 'reviewer']);

        return $this->handleSuccessResponse($message, new WithdrawalRequestResource($withdrawal));
    }
}

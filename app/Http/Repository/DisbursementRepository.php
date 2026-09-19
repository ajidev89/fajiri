<?php

namespace App\Http\Repository;

use App\Enums\Disbursement\Status;
use App\Http\Repository\Contracts\DisbursementRepositoryInterface;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\AuthUserTrait;
use App\Models\Disbursement;
use Exception;

class DisbursementRepository implements DisbursementRepositoryInterface
{
    use AuthUserTrait;

    protected $cloudinaryService;
    protected $currencyService;

    public function __construct(CloudinaryService $cloudinaryService, \App\Services\CurrencyService $currencyService)
    {
        $this->cloudinaryService = $cloudinaryService;
        $this->currencyService = $currencyService;
    }

    public function all($request = null)
    {
        $user = $this->user();
        $query = Disbursement::with(['disbursable', 'requestedBy', 'disbursedBy']);

        if (! in_array($user->role->slug ?? '', ['admin', 'super-admin'], true)) {
            $query->where('requested_by', $user->id);
        }

        if ($request && $request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('disbursement_code', 'like', "%{$term}%")
                    ->orWhere('beneficiary_name', 'like', "%{$term}%");
            });
        }

        $sortBy = $request && in_array($request->input('sort_by'), ['created_at', 'updated_at', 'amount', 'status', 'beneficiary_name', 'disbursement_code'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        return $query->orderBy($sortBy, $sortOrder)
            ->paginate($request?->per_page ?? 20);
    }

    public function find($id)
    {
        return Disbursement::with(['disbursable', 'requestedBy', 'disbursedBy'])->findOrFail($id);
    }

    public function request(array $data)
    {
        $data['requested_by'] = $this->user()->id;
        $data['status'] = Status::PENDING;

        // Calculate conversion to NGN for base tracking
        $currency = $data['currency'] ?? 'NGN';
        $rate = $this->currencyService->getExchangeRate($currency, 'NGN');
        $data['rate'] = $rate;
        $data['converted_amount'] = round($data['amount'] * $rate, 2);
        
        return Disbursement::create($data);
    }

    public function disburse($id, $proofFile)
    {
        $disbursement = $this->find($id);

        if ($disbursement->status !== Status::PENDING) {
            throw new Exception("Only pending disbursements can be processed.");
        }

        $upload = $this->cloudinaryService->uploadImage($proofFile, 'disbursements/proofs');
        $proofUrl = $upload['url'];

        $disbursement->update([
            'status' => Status::COMPLETED,
            'proof_of_payment' => $proofUrl,
            'disbursed_by' => $this->user()->id,
        ]);

        return $disbursement;
    }

    public function reject($id, $reason)
    {
        $disbursement = $this->find($id);

        if ($disbursement->status !== Status::PENDING) {
            throw new Exception("Only pending disbursements can be rejected.");
        }

        $disbursement->update([
            'status' => Status::REJECTED,
            'rejected_reason' => $reason,
            'disbursed_by' => $this->user()->id,
        ]);

        return $disbursement;
    }
}

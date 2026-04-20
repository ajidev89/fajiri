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

    public function all()
    {
        $user = $this->user();
        if ($user->role->slug === 'admin') {
            return Disbursement::with(['disbursable', 'requestedBy', 'disbursedBy'])->latest()->get();
        }

        return Disbursement::with(['disbursable', 'requestedBy', 'disbursedBy'])
            ->where('requested_by', $user->id)
            ->latest()
            ->get();
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

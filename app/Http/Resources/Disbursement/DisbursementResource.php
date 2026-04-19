<?php

namespace App\Http\Resources\Disbursement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisbursementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disbursable_type' => $this->disbursable_type,
            'disbursable' => $this->disbursable,
            'requested_by' => $this->requestedBy ? [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->profile ? $this->requestedBy->profile->first_name . ' ' . $this->requestedBy->profile->last_name : $this->requestedBy->username,
            ] : null,
            'disbursed_by' => $this->disbursedBy ? [
                'id' => $this->disbursedBy->id,
                'name' => $this->disbursedBy->profile ? $this->disbursedBy->profile->first_name . ' ' . $this->disbursedBy->profile->last_name : $this->disbursedBy->username,
            ] : null,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'beneficiary_name' => $this->beneficiary_name,
            'payment_method' => $this->payment_method,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_name,
            'status' => $this->status,
            'proof_of_payment' => $this->proof_of_payment,
            'rejected_reason' => $this->rejected_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

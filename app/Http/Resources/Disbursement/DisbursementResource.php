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
            'id'                         => $this->id,
            'disbursement_code'          => $this->disbursement_code,
            'disbursable_type'           => $this->disbursable_type,
            'disbursable_id'             => $this->disbursable_id,
            'disbursable'                => $this->disbursable ? [
                'id'       => $this->disbursable->id,
                'title'    => $this->disbursable->title ?? $this->disbursable->name ?? 'Campaign',
                'currency' => $this->disbursable->currency ?? 'NGN',
            ] : null,
            'requested_by'               => $this->requestedBy ? [
                'id'       => $this->requestedBy->id,
                'name'     => $this->requestedBy->profile ? trim($this->requestedBy->profile->first_name . ' ' . $this->requestedBy->profile->last_name) : $this->requestedBy->username,
                'email'    => $this->requestedBy->email,
                'kyc'      => $this->requestedBy->kyc?->status ?? 'unverified',
            ] : null,
            'disbursed_by'               => $this->disbursedBy ? [
                'id'       => $this->disbursedBy->id,
                'name'     => $this->requestedBy ? trim(($this->disbursedBy->profile->first_name ?? '') . ' ' . ($this->disbursedBy->profile->last_name ?? '')) : 'Admin',
            ] : null,
            'recipient_type'             => is_string($this->recipient_type) ? $this->recipient_type : $this->recipient_type?->value,
            'recipient_country'          => $this->recipient_country ?? 'NG',
            'recipient_email'            => $this->recipient_email,
            'recipient_phone'            => $this->recipient_phone,
            'amount'                     => (float) $this->amount,
            'fee_amount'                 => (float) ($this->fee_amount ?? 0),
            'fee_bearer'                 => $this->fee_bearer ?? 'campaign',
            'net_amount'                 => (float) ($this->net_amount ?? $this->amount),
            'currency'                   => $this->currency ?? 'NGN',
            'target_currency'            => $this->target_currency ?? $this->currency ?? 'NGN',
            'rate'                       => (float) ($this->rate ?? 1.0),
            'estimated_recipient_amount' => (float) ($this->estimated_recipient_amount ?? $this->net_amount ?? $this->amount),
            'beneficiary_name'           => $this->beneficiary_name,
            'payment_method'             => is_string($this->payment_method) ? $this->payment_method : $this->payment_method?->value,
            'account_name'               => $this->account_name,
            'account_number'             => $this->account_number,
            'destination_mask'           => $this->destination_mask ?? ($this->bank_name . ' •••• ' . substr($this->account_number, -4)),
            'bank_name'                  => $this->bank_name,
            'bank_code'                  => $this->bank_code,
            'routing_number'             => $this->routing_number,
            'swift_bic'                  => $this->swift_bic,
            'iban'                       => $this->iban,
            'status'                     => is_string($this->status) ? $this->status : $this->status?->value,
            'purpose'                    => $this->purpose,
            'purpose_description'        => $this->purpose_description,
            'documents'                  => $this->documents ?? [],
            'compliance_checks'          => $this->compliance_checks ?? [],
            'risk_score'                 => $this->risk_score ?? 0,
            'risk_level'                 => is_string($this->risk_level) ? $this->risk_level : $this->risk_level?->value ?? 'low',
            'security_auth_method'       => $this->security_auth_method ?? 'password',
            'payout_provider'            => $this->payout_provider,
            'provider_reference'         => $this->provider_reference,
            'proof_of_payment'           => $this->proof_of_payment,
            'rejected_reason'            => $this->rejected_reason,
            'status_history'             => $this->status_history ?? [],
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}


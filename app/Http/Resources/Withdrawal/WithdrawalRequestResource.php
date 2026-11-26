<?php

namespace App\Http\Resources\Withdrawal;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawalRequest
 */
class WithdrawalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->hasPermission('financial_records');

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'account' => $this->account_snapshot,
            'payout_reference' => $this->payout_reference,
            'proof_url' => $this->proof_url,
            'rejection_reason' => $this->rejection_reason,
            'admin_note' => $this->when($isAdmin, $this->admin_note),
            'user' => $this->when($isAdmin && $this->relationLoaded('user'), fn () => [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'member_id' => $this->user->member_id,
                'name' => trim(($this->user->profile->first_name ?? '').' '.($this->user->profile->last_name ?? '')) ?: null,
                'country' => $this->user->country?->name,
                'wallet_balance' => (float) ($this->user->wallet?->balance ?? 0),
                'kyc_status' => $this->user->kyc?->status instanceof \BackedEnum ? $this->user->kyc->status->value : $this->user->kyc?->status,
            ]),
            'reviewed_by' => $this->when($isAdmin && $this->relationLoaded('reviewer'), fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'email' => $this->reviewer->email,
            ] : null),
            'approved_at' => $this->approved_at,
            'paid_at' => $this->paid_at,
            'rejected_at' => $this->rejected_at,
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
        ];
    }
}

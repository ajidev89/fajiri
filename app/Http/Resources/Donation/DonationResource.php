<?php

namespace App\Http\Resources\Donation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    use \App\Http\Traits\ConvertedAmountTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $converted = $this->getConvertedAmount($this->amount, $this->currency, $request);

        $isCampaign = $this->donatable_type === \App\Models\Campaign::class;

        return [
            "id"              => $this->id,
            "name"            => $this->name ?? ($this->user?->profile?->first_name ? $this->user->profile->first_name . ' ' . $this->user->profile->last_name : "Anonymous"),
            "email"           => $this->email ?? $this->user?->email ?? "Anonymous",
            "user_id"         => $this->user_id,
            "medium"          => $this->medium,
            "donatable"       => $this->donatable,
            "donatable_type"  => $this->donatable_type,
            "type"            => $isCampaign ? 'campaign' : 'need',
            "title"           => $isCampaign ? $this->donatable?->title : $this->donatable?->name,
            "amount"          => $converted['amount'],
            "currency"        => $converted['currency'],
            "base_amount"     => $converted['base_amount'],
            "base_currency"   => $converted['base_currency'],
            "base_amount_usd" => (float) ($this->base_amount_usd ?? 0.00),
            "status"          => $this->status,
            "reference"       => $this->reference,
            "rate"            => $this->rate !== null ? (float) $this->rate : null,
            "converted_amount" => $this->converted_amount !== null ? (float) $this->converted_amount : null,
            "is_flagged"      => $this->flagged_at !== null,
            "flagged_at"      => $this->flagged_at,
            "flag_reason"     => $this->flag_reason,
            "flagged_by"      => $this->whenLoaded('flaggedBy', fn () => $this->flaggedBy ? [
                'id'    => $this->flaggedBy->id,
                'email' => $this->flaggedBy->email,
            ] : null),
            "created_at"      => $this->created_at,
            "updated_at"      => $this->updated_at,
            "deleted_at"      => $this->deleted_at
        ];
    }
}

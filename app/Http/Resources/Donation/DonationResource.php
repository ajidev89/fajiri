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

        return [
            "id" => $this->id,
            "name" => $this->name ?? $this->user?->profile?->first_name . ' ' . $this->user?->profile?->last_name ?? "Anonymous",
            "email" => $this->email ?? $this->user?->email ?? "Anonymous",
            "user_id" => $this->user_id,
            "medium" => $this->medium,
            "donatable" => $this->donatable,
            "donatable_type" => $this->donatable_type,
            "amount" => $converted['amount'],
            "currency" => $converted['currency'],
            "base_amount" => $converted['base_amount'],
            "base_currency" => $converted['base_currency'],
            "status" => $this->status,
            "reference" => $this->reference,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "deleted_at" => $this->deleted_at
        ];
    }
}

<?php

namespace App\Http\Resources\Donation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->user->profile->first_name . ' ' . $this->user->profile->last_name,
            "user_id" => $this->user_id,
            "donatable" => $this->donatable,
            "donatable_type" => $this->donatable_type,
            "amount" => $this->amount,
            "converted_amount" => $this->converted_amount,
            "currency" => $this->currency,
            "status" => $this->status,
            "reference" => $this->reference,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "deleted_at" => $this->deleted_at
        ];
    }
}

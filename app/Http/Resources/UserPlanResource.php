<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->pivot->id,
            'plan_id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'amount' => $this->pivot->amount,
            'payment_currency' => $this->pivot->currency,
            'started_at' => $this->pivot->started_at,
            'expires_at' => $this->pivot->expires_at,
            'is_lifetime' => $this->pivot->expires_at === null,
            'status' => $this->pivot->status,
            'auto_renew' => $this->pivot->auto_renew,
        ];
    }
}

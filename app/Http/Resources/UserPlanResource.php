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
            'started_at' => $this->pivot->started_at,
            'expires_at' => $this->pivot->expires_at,
            'status' => $this->pivot->status,
            'auto_renew' => $this->pivot->auto_renew,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new User\UserResource($this->whenLoaded('addedBy')),
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'campaign_type' => $this->campaign_type,
            'images' => $this->images,
            'status' => $this->status,
            'end_date' => $this->end_date,
            'goal_amount' => $this->goal_amount,
            'goal_amount_converted' => $this->goal_amount_in_user_currency,
            'collected_amount' => $this->collected_amount,
            'collected_amount_converted' => $this->collected_amount_in_user_currency,
            'currency' => $this->currency ?? 'NGN',
            'target_currency' => auth()->user()->wallet->currency ?? 'NGN',
            'donors_count' => $this->donors_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

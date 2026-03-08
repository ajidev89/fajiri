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
            'user' => new User\UserResource($this->whenLoaded('user')),
            'title' => $this->title,
            'body' => $this->body,
            'images' => $this->images,
            'goal_amount' => $this->goal_amount,
            'collected_amount' => $this->collected_amount,
            'donors_count' => $this->donors_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

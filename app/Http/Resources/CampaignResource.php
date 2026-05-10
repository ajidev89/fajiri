<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    use \App\Http\Traits\ConvertedAmountTrait;

    public function toArray(Request $request): array
    {
        $convertedGoal = $this->getConvertedAmount($this->goal_amount, $this->currency, $request);
        $convertedCollected = $this->getConvertedAmount($this->collected_amount, $this->currency, $request);

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
            'goal_amount' => $convertedGoal['amount'],
            'collected_amount' => $convertedCollected['amount'],
            'currency' => $convertedGoal['currency'],
            'base_goal_amount' => $convertedGoal['base_amount'],
            'base_collected_amount' => $convertedCollected['base_amount'],
            'base_currency' => $convertedGoal['base_currency'],
            'donors_count' => $this->donors_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

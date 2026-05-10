<?php

namespace App\Http\Resources\Need;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NeedResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'age' => $this->age,
            'location' => $this->location,
            'amount' => $converted['amount'],
            'currency' => $converted['currency'],
            'base_amount' => $converted['base_amount'],
            'base_currency' => $converted['base_currency'],
            'description' => $this->description,
            'image' => $this->image,
            'urgency' => $this->urgency,
            'added_by' => new \App\Http\Resources\User\UserResource($this->whenLoaded('addedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

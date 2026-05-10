<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\URL;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    use \App\Http\Traits\ConvertedAmountTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        $converted = $this->getConvertedAmount($this->price, $this->currency, $request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'account_type' => $this->account_type,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $converted['amount'],
            'currency' => $converted['currency'],
            'base_price' => $converted['base_amount'],
            'base_currency' => $converted['base_currency'],
            'duration' => $this->duration,
            'features' => $this->features,
            'status' => $this->status,
            'stripe_price_id' => $this->stripe_price_id,
            'stripe_product_id' => $this->stripe_product_id,
            'paystack_plan_code' => $this->paystack_plan_code,
        ];
    }
}

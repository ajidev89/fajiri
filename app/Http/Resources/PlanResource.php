<?php

namespace App\Http\Resources;

use App\Http\Traits\ConvertedAmountTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    use ConvertedAmountTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $converted = $this->getConvertedAmount($this->price, $this->currency, $request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'account_type' => $this->account_type,
            'sub_account_type' => $this->sub_account_type,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $converted['amount'],
            'currency' => $converted['currency'],
            'base_price' => $converted['base_amount'],
            'base_currency' => $converted['base_currency'],
            'duration' => null,
            'billing_type' => 'one_time',
            'is_lifetime' => true,
            'features' => $this->features,
            'status' => $this->status,
            'stripe_price_id' => $this->stripe_price_id,
            'stripe_product_id' => $this->stripe_product_id,
            'paystack_plan_code' => $this->paystack_plan_code,
        ];
    }
}

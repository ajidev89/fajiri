<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'duration' => $this->duration,
            'features' => $this->features,
            'status' => $this->status,
            'rc_entitlement_id' => $this->rc_entitlement_id,
            'rc_offering_id' => $this->rc_offering_id,
            'rc_package_id' => $this->rc_package_id,
            'rc_product_id_ios' => $this->rc_product_id_ios,
            'rc_product_id_android' => $this->rc_product_id_android,
        ];
    }
}

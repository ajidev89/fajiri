<?php

namespace App\Http\Resources\Insurance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceResource extends JsonResource
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
            "name" => $this->name,
            "slug" => $this->slug,
            "website" => $this->website,
            "logo" => $this->logo,
            "phone" => $this->phone,
            "email" => $this->email,
            "address" => $this->address,
            "description" => $this->description,
            "type" => $this->type,
            "city" => $this->city,
            "state" => $this->state,
            "country" => $this->country,
            "status" => $this->status,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
        ];
    }
}

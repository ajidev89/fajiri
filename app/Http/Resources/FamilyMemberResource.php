<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FamilyMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'full_name' => $this->full_name,
            'dob' => $this->dob?->format('Y-m-d'),
            'gender' => $this->gender,
            'photo' => $this->photo,
            'relationship' => $this->relationship,
            'married_date' => $this->married_date?->format('Y-m-d'),
            'is_alive' => (bool) $this->is_alive,
            'death_date' => $this->death_date?->format('Y-m-d'),
            'note' => $this->note,
            'children' => FamilyMemberResource::collection($this->whenLoaded('children')),
            'parent' => new FamilyMemberResource($this->whenLoaded('parent')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

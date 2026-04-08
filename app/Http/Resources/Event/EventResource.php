<?php

namespace App\Http\Resources\Event;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'image' => $this->image,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'category' => $this->category,
            'added_by' => $this->addedBy,
            'attendees_count' => $this->attendees()->count(),
            'slots_available' => $this->slots_available,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

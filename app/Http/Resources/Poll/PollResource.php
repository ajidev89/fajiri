<?php

namespace App\Http\Resources\Poll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'type'               => $this->type?->value,
            'status'             => $this->status?->value,
            'start_date'         => $this->start_date?->toDateTimeString(),
            'duration_hours'     => $this->duration_hours,
            'ends_at'            => $this->ends_at?->toDateTimeString(),
            'time_left'          => $this->time_left,
            'participants_count' => $this->participants_count,
            'views'              => $this->views,
            'options'            => PollOptionResource::collection($this->whenLoaded('options')),
            'added_by'           => $this->when($this->relationLoaded('addedBy'), fn() => [
                'id'   => $this->addedBy?->id,
                'name' => $this->addedBy?->profile?->first_name . ' ' . $this->addedBy?->profile?->last_name,
            ]),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}

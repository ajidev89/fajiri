<?php

namespace App\Http\Resources\Poll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user'          => $this->when($this->relationLoaded('user'), fn() => [
                'id'         => $this->user?->id,
                'name'       => $this->user?->profile?->first_name . ' ' . $this->user?->profile?->last_name,
                'member_id'  => $this->user?->member_id,
                'avatar'     => $this->user?->profile?->avatar,
            ]),
            'option'        => $this->when($this->relationLoaded('option'), fn() => [
                'id'    => $this->option?->id,
                'label' => $this->option?->label,
            ]),
            'text_response' => $this->text_response,
            'answered_at'   => $this->created_at?->diffForHumans(),
            'created_at'    => $this->created_at,
        ];
    }
}

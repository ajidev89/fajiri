<?php

namespace App\Http\Resources\Poll;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalVotes = $this->poll->responses()->count();

        return [
            'id'              => $this->id,
            'label'           => $this->label,
            'order'           => $this->order,
            'votes_count'     => $this->responses()->count(),
            'vote_percentage' => $totalVotes > 0
                ? round(($this->responses()->count() / $totalVotes) * 100, 1)
                : 0,
        ];
    }
}

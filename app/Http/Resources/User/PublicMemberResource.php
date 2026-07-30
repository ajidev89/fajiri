<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"                => $this->id,
            "member_id"         => $this->member_id,
            "username"          => $this->username,
            "profile"           => [
                "first_name" => $this->profile?->first_name,
                "last_name"  => $this->profile?->last_name,
                "avatar"     => $this->profile?->avatar,
            ],
            "account_type"      => $this->account_type,
            "status"            => $this->status,
        ];
    }
}

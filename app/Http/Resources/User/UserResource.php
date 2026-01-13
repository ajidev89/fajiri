<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            "email"             => $this->email,
            "email_verified_at" => $this->email_verified_at,
            "phone"             => $this->phone,
            "role"              => $this->role,
            "phone_verified_at" => $this->phone_verified_at,
            "account_type"      => $this->account_type,
            "notification_token" => $this->notification_token,
            "created_at"        => $this->created_at,
            "updated_at"        => $this->updated_at,
            "deleted_at"        => $this->deleted_at
        ];
    }
}

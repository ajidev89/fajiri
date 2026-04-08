<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Profile\ProfileResource;
use App\Http\Resources\Wallet\WalletResource;
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
            "username"          => $this->username,
            "profile"           => new ProfileResource($this->profile), 
            "wallet"            => new WalletResource($this->wallet),
            "phone_verified_at" => $this->phone_verified_at,
            "account_type"      => $this->account_type,
            "last_login_at"     => $this->last_login_at,
            "status"            => $this->status,
            "created_at"        => $this->created_at,
            "updated_at"        => $this->updated_at,
            "deleted_at"        => $this->deleted_at
        ];
    }
}

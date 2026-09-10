<?php

namespace App\Http\Resources\Leaderboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaderboardResource extends JsonResource
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
            'member_id' => $this->member_id,
            'email' => $this->email,
            'username' => $this->username,
            'email_verified_at' => $this->email_verified_at,
            'phone' => $this->phone,
            "profile" => [
                'first_name' => $this->profile->first_name,
                'last_name' => $this->profile->last_name,
                'middle_name' => $this->profile->middle_name,
                'gender' => $this->profile->gender,
                'occupation' => $this->profile->occupation,
                'avatar' => $this->profile->avatar,
                'created_at' => $this->profile->created_at,
                'updated_at' => $this->profile->updated_at,
            ],
            "country_iso" => $this->country->iso2,
            "total_engagement" => $this->total_engagement,
            "campaign_donations_count" => $this->campaign_donations_count,
            "need_donations_count" => $this->need_donations_count,
            "event_attendance_count" => $this->event_attendance_count,
            "referrals_count" => $this->referrals_count,
            'role_id' => $this->role_id,
            'country_id' => $this->country_id,
            'country' => $this->country,
            'account_type' => $this->account_type,
            'sub_account_type' => $this->sub_account_type,
            'status' => $this->status,
            'referral_code' => $this->referral_code,
            'referred_by' => $this->referred_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

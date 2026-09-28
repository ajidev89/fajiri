<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Profile\ProfileResource;
use App\Http\Resources\UserPlanResource;
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
        $plan = $this->currentPlan();
        $referralCount = $this->referrals()->count();
        $accountType = $this->account_type instanceof \BackedEnum
            ? $this->account_type->value
            : $this->account_type;

        return [
            'id' => (string) $this->id,
            'email' => (string) $this->email,
            'is_subscribed' => $plan !== null,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'phone' => $this->phone,
            'member_id' => $this->member_id,
            'referral_code' => $this->member_id,
            'country' => $this->country?->name,
            'country_id' => $this->country_id !== null ? (int) $this->country_id : null,
            'country_data' => $this->countryData(),
            'preferred_currency_code' => $this->wallet?->currency ?? $this->country?->currency,
            'role' => $this->rolePayload(),
            'username' => $this->username,
            'has_pin' => (bool) $this->pin,
            'profile' => $this->profile ? new ProfileResource($this->profile) : null,
            'wallet' => $this->walletPayload(),
            'phone_verified_at' => $this->phone_verified_at?->toISOString(),
            'account_type' => (string) ($accountType ?? ''),
            'last_login_at' => $this->last_login_at,
            'status' => $this->status,
            'referral_count' => $referralCount,
            'referrals_count' => $referralCount,
            'total_donations' => (float) $this->donations()->where('status', 'completed')->sum('amount'),
            'donations_count' => $this->donations()->where('status', 'completed')->count(),
            'unread_notification' => $this->unreadNotifications()->count(),
            'plan' => $plan ? new UserPlanResource($plan) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rolePayload(): ?array
    {
        if ($this->role === null) {
            return null;
        }

        return [
            'id' => (int) $this->role->id,
            'name' => (string) $this->role->name,
            'slug' => (string) $this->role->slug,
            'created_at' => $this->role->created_at?->toISOString(),
            'updated_at' => $this->role->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function countryData(): ?array
    {
        $country = $this->country;

        if ($country === null) {
            return null;
        }

        return [
            'id' => (int) $country->id,
            'name' => $country->name,
            'iso2' => $country->iso2,
            'iso3' => $country->iso3,
            'phone_code' => $country->phone_code,
            'currency' => $country->currency,
            'flag' => $country->flag,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function walletPayload(): ?array
    {
        if ($this->wallet === null) {
            return null;
        }

        return [
            ...(new WalletResource($this->wallet))->resolve(),
            'has_pin' => (bool) $this->pin,
        ];
    }
}

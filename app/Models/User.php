<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasUuids, HasFactory, Notifiable, HasUuids, HasApiTokens, \App\Traits\HasWallet;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'email_verified_at',
        'phone',
        'phone_verified_at',
        'account_type',
        'sub_account_type',
        'notification_token',
        'password',
        "role_id",
        "country_id",
        "pin",
        "status",
        "referral_code",
        "referred_by"
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->referral_code) {
                $user->referral_code = static::generateUniqueReferralCode();
            }
        });
    }

    public static function generateUniqueReferralCode()
    {
        do {
            $code = 'FAJ-' . strtoupper(Str::random(6));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function markPhoneAsVerified(){
        return $this->forceFill([
            'phone_verified_at' => now(),
        ])->save();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function kyc(): HasOne
    {
        return $this->hasOne(Kyc::class, 'user_id', 'id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'user_id', 'id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'added_by');
    }

    public function needs(): HasMany
    {
        return $this->hasMany(Need::class, 'added_by');
    }

    public function scopeFundraisers($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'fundraiser');
        });
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'user_plans')
            ->withPivot(['id', 'started_at', 'expires_at', 'status', 'auto_renew'])
            ->withTimestamps();
    }

    public function currentPlan()
    {
        return $this->plans()->where('user_plans.status', 'active')->latest('user_plans.started_at')->first();
    }

    public function preference(): HasOne
    {
        return $this->hasOne(Preference::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function canUsePaystack() {
        return $this->country->currency === 'NGN';
    }

    public function withdrawalAccounts(): HasMany
    {
        return $this->hasMany(WithdrawalAccount::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function eventAttendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }
}

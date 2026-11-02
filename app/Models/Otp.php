<?php

namespace App\Models;

use App\Enums\Otp\Channel;
use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Otp extends Model
{
    use Observable;

    public const RESEND_BLOCKED = 'Please wait until the current code is used or a minute has passed before requesting another OTP.';

    public $fillable = [
        'channel',
        'identifier',
        'hash',
        'expires_at',
        'verified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified' => 'boolean',
        ];
    }

    public function verify(string $code): bool
    {
        return Hash::check($code, $this->hash);
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * The unused OTP that blocks a new code, or null when a new code is allowed.
     */
    public static function canIssue(string $identifier, string $channel): ?self
    {
        $otp = static::latestUnused($identifier, $channel);

        if ($otp === null || $otp->created_at->lte(now()->subMinute())) {
            return null;
        }

        return $otp;
    }

    public static function markVerified(string $identifier, string $channel): void
    {
        static::latestUnused($identifier, $channel)?->update(['verified' => true]);
    }

    protected static function latestUnused(string $identifier, string $channel): ?self
    {
        $query = static::query()
            ->where('channel', $channel)
            ->where('verified', false);

        if ($channel === Channel::EMAIL->value) {
            $query->whereRaw('LOWER(identifier) = ?', [strtolower($identifier)]);
        } else {
            $query->where('identifier', $identifier);
        }

        return $query->latest()->first();
    }
}

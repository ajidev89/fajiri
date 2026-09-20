<?php

namespace App\Models;

use App\Http\Traits\Observable;
use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Need extends Model
{
    use HasUuids, Observable;

    /**
     * Donations are settled in mixed currencies, so `base_amount_usd` is the
     * only comparable figure to aggregate across them.
     */
    public const BASE_CURRENCY = 'USD';

    protected $fillable = [
        'name',
        'age',
        'location',
        'currency',
        'amount',
        'description',
        'image',
        'urgency',
        'added_by',
    ];

    protected $appends = [
        'collected_amount',
    ];

    public function donations(): MorphMany
    {
        return $this->morphMany(Donation::class, 'donatable');
    }

    public function completedDonations(): MorphMany
    {
        return $this->donations()->where('status', 'completed');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function getCollectedAmountAttribute(): float
    {
        if (array_key_exists('donations_sum_base_amount_usd', $this->attributes)) {
            return (float) $this->attributes['donations_sum_base_amount_usd'];
        }

        return (float) $this->completedDonations()->sum('base_amount_usd');
    }

    public function collectedAmountIn(?string $currency): float
    {
        return app(CurrencyService::class)->convert(
            $this->collected_amount,
            self::BASE_CURRENCY,
            $currency ?: self::BASE_CURRENCY
        );
    }
}

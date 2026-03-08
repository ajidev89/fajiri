<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'added_by',
        'title',
        'body',
        'type',
        'campaign_type',
        'status',
        'images',
        'goal_amount',
        'currency',
    ];

    protected $casts = [
        'images' => 'array',
        'goal_amount' => 'float',
        'type' => \App\Enums\Campagin\Type::class,
        'campaign_type' => \App\Enums\Campagin\CampaignType::class,
        'status' => \App\Enums\Campagin\Status::class,
    ];

    /**
     * Get the goal amount converted to the authenticated user's currency.
     */
    public function getGoalAmountInUserCurrencyAttribute(): float
    {
        $userCurrency = auth()->user()->wallet->currency ?? 'NGN';
        return app(\App\Services\CurrencyService::class)->convert(
            $this->goal_amount, 
            $this->currency ?? 'NGN', 
            $userCurrency
        );
    }

    /**
     * Get the collected amount converted to the authenticated user's currency.
     */
    public function getCollectedAmountInUserCurrencyAttribute(): float
    {
        $userCurrency = auth()->user()->wallet->currency ?? 'NGN';
        return app(\App\Services\CurrencyService::class)->convert(
            $this->collected_amount, 
            $this->currency ?? 'NGN', 
            $userCurrency
        );
    }

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $appends = [
        'collected_amount',
        'donors_count',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function getCollectedAmountAttribute(): float
    {
        return $this->donations()->where('status', 'completed')->sum('converted_amount');
    }

    public function getDonorsCountAttribute(): int
    {
        return $this->donations()->where('status', 'completed')->distinct('user_id')->count('user_id');
    }
}

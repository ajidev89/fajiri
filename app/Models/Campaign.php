<?php

namespace App\Models;

use App\Enums\Campagin\CampaignType;
use App\Enums\Campagin\Status;
use App\Enums\Campagin\Type;
use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'added_by',
        'title',
        'body',
        'type',
        'category_id',
        'campaign_type',
        'status',
        'images',
        'goal_amount',
        'currency',
        'end_date',
        'age',
        'location',
    ];

    protected $casts = [
        'images' => 'array',
        'goal_amount' => 'float',
        'type' => Type::class,
        'campaign_type' => CampaignType::class,
        'status' => Status::class,
        'end_date' => 'datetime',
    ];

    /**
     * Get the goal amount converted to the authenticated user's currency.
     */
    public function getGoalAmountInUserCurrencyAttribute(): float
    {
        $userCurrency = auth()->user()->wallet->currency ?? 'NGN';

        return app(CurrencyService::class)->convert(
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

        return app(CurrencyService::class)->convert(
            $this->collected_amount,
            $this->currency ?? 'NGN',
            $userCurrency
        );
    }

    // protected $attributes = [
    //     'status' => 'pending',
    // ];

    protected $appends = [
        'collected_amount',
        'donors_count',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function donations(): MorphMany
    {
        return $this->morphMany(Donation::class, 'donatable');
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

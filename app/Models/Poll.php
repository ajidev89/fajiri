<?php

namespace App\Models;

use App\Enums\Poll\PollStatus;
use App\Enums\Poll\PollType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Poll extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'status',
        'start_date',
        'duration_hours',
        'added_by',
        'views',
    ];

    protected $casts = [
        'status'     => PollStatus::class,
        'type'       => PollType::class,
        'start_date' => 'datetime',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    /**
     * Returns the time left until the poll ends in human-readable format,
     * or 'Ended' if the poll has expired.
     */
    public function getTimeLeftAttribute(): string
    {
        $endsAt = Carbon::parse($this->start_date)->addHours($this->duration_hours);

        if (now()->greaterThan($endsAt)) {
            return 'Ended';
        }

        return now()->diffForHumans($endsAt, true);
    }

    /**
     * Returns the number of unique users who have responded to this poll.
     */
    public function getParticipantsCountAttribute(): int
    {
        return $this->responses()->distinct('user_id')->count('user_id');
    }

    /**
     * Returns a brief list of up to 4 participants.
     */
    public function getParticipantsAttribute()
    {
        return $this->responses()
            ->select('user_id')
            ->distinct()
            ->take(4)
            ->with('user.profile')
            ->get()
            ->map(fn($response) => [
                'id'     => $response->user?->id,
                'name'   => trim($response->user?->profile?->first_name . ' ' . $response->user?->profile?->last_name),
                'avatar' => $response->user?->profile?->avatar,
            ]);
    }

    /**
     * Returns the datetime when the poll ends.
     */
    public function getEndsAtAttribute(): Carbon
    {
        return Carbon::parse($this->start_date)->addHours($this->duration_hours);
    }
}

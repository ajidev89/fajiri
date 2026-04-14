<?php

namespace App\Models;

use App\Enums\Event\EventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'added_by',
        'category_id',
        'title',
        'slug',
        'description',
        'location',
        'start_date',
        'end_date',
        'image',
        'status',
        'is_featured',
        'slots',
        'amount',
    ];

    protected $casts = [
        'status' => EventStatus::class,
        'is_featured' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function getSlotsAvailableAttribute()
    {
        return $this->slots - $this->attendees()->count();
    }
}

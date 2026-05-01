<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'level',
        'account_type',
        'slug',
        'description',
        'price',
        'currency',
        'duration',
        'features',
        'status',
        'rc_entitlement_id',
        'rc_offering_id',
        'rc_package_id',
        'rc_product_id_ios',
        'rc_product_id_android',
    ];

    protected $casts = [
        'features' => 'array',
        'status' => 'boolean',
        'price' => 'decimal:2',
        'account_type' => \App\Enums\User\AccountType::class,
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_plans')
            ->withPivot(['id', 'started_at', 'expires_at', 'status'])
            ->withTimestamps();
    }

    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeAccountType($query, $accountType)
    {
        return $query->where('account_type', $accountType);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parent_id',
        'full_name',
        'dob',
        'gender',
        'photo',
        'relationship',
        'married_date',
        'is_alive',
        'death_date',
        'note',
    ];

    protected $casts = [
        'dob' => 'date',
        'married_date' => 'date',
        'death_date' => 'date',
        'is_alive' => 'boolean',
    ];

    /**
     * Get the user that owns the family member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent of this family member.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'parent_id');
    }

    /**
     * Get the children of this family member.
     */
    public function children(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'parent_id');
    }
}

<?php

namespace App\Models;

use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Initiative extends Model
{
    use HasUuids, Observable;

    protected $fillable = [
        'title',
        'description',
        'image',
        'status',
        'added_by',
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}

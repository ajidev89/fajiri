<?php

namespace App\Models;

use App\Http\Traits\SluggableTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Ambassador extends Model
{
    use HasUuids, SluggableTrait;

    protected $fillable = [
        'name',
        'slug',
        'title',
        'biography',
        'photo',
        'sort_order',
    ];

    public $sluggable = [
        'source' => 'name',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}

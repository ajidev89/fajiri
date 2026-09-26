<?php

namespace App\Models;

use App\Http\Traits\SluggableTrait;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Testimony extends Model
{
    use HasUuids, SluggableTrait;

    protected $fillable = [
        'name',
        'slug',
        'age',
        'story',
        'photo',
        'sort_order',
    ];

    public $sluggable = [
        'source' => 'name',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}

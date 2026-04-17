<?php

namespace App\Models;

use App\Http\Traits\SluggableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory, SluggableTrait;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'about',
        'website',
        'focus_areas',
        'impact',
    ];

    protected $casts = [
        'focus_areas' => 'array',
        'impact' => 'array',
    ];

    public $sluggable = [
        'source' => 'name',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'content',
        'image_url',
        'target_audience',
    ];

    protected function casts(): array
    {
        return [
            'target_audience' => 'array',
        ];
    }
}

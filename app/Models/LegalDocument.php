<?php

namespace App\Models;

use App\Enums\Legal\Type;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'title',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'type' => Type::class,
        ];
    }
}

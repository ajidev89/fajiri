<?php

namespace App\Models;

use App\Enums\Faq\Type;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'question',
        'answer',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => Type::class,
            'sort_order' => 'integer',
        ];
    }
}

<?php

namespace App\Models;

use App\Http\Traits\SluggableTrait;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use SluggableTrait;

    public $fillable = [
        "name",
        'slug'
    ];

   /**
     * @var array|string[]
     */
    public array $sluggable = [
        'source' => 'name'
    ];
}

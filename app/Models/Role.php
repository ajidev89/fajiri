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

    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}

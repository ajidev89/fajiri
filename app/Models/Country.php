<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'phone_code',
        'currency',
        'flag',
        'status',
    ];
}

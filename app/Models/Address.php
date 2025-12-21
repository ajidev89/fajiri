<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    public $fillable = [
        "user_id",
        "line_1",
        "line_2",
        "city",
        "state",
        "postal_code",
        "country_id"
    ];
}

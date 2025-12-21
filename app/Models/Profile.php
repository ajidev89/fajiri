<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasUuids;

    public $fillable = [
        "user_id",
        "first_name",
        "last_name",
        "middle_name",
        "dob",
        "gender"
    ];
}

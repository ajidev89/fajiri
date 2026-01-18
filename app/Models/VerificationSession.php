<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VerificationSession extends Model
{
     
    use HasUuids;

    public $fillable = [
        "user_id",
        "provider",
        "session_id",
        "status"
    ];

}

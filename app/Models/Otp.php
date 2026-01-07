<?php

namespace App\Models;

use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Otp extends Model
{
    use Observable;
    public $fillable = [
        "channel",
        "identifier",
        "hash",
        "expires_at",
        "verified"
    ];

    public function verify($code){
        return Hash::check($code, $this->hash);
    }

    public function isExpired()
    {
        return $this->expires_at <= now();
    }
}

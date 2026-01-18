<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasUuids;
    
    public $fillable = [
        "user_id",
        "line_1",
        "line_2",
        "city",
        "state",
        "postal_code",
        "country_id"
    ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function country(){
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
}

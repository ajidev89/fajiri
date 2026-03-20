<?php

namespace App\Models;

use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    use HasUuids, Observable;
    public $fillable = [
        "name",
        "slug",
        "website",
        "logo",
        "phone",
        "email",
        "address",
        "description",
        "type",
        "city",
        "state",
        "country_id",
        "status",
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}

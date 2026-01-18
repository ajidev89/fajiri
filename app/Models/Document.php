<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    
    use HasUuids;
    
    public $fillable = [
        "user_id",
        "provider",
        "verification_session_id",
        "type",
        "url",
        "name",
        "mimetype"
    ]; 
}

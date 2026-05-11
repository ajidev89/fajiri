<?php

namespace App\Http\Traits;

use App\Models\User;

trait AuthUserTrait
{
    public function user(): User | null
    {
        return request()->user() ?? auth('sanctum')->user();
    }
}

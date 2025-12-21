<?php

namespace App\Enums\User;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;
    
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';
    case DEACTIVATED = 'deactivated';
    case BANNED = 'banned';

}

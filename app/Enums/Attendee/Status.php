<?php

namespace App\Enums\Attendee;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

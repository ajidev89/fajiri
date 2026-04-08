<?php

namespace App\Enums\Event;

use App\Http\Traits\EnumTrait;

enum EventStatus: string
{
    use EnumTrait;

    case UPCOMING = 'upcoming';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

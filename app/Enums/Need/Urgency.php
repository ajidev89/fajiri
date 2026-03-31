<?php

namespace App\Enums\Need;

use App\Http\Traits\EnumTrait;

enum Urgency: string
{
    use EnumTrait;

    case LOW = 'low';

    case MEDIUM = 'medium';

    case HIGH = 'high';

}
<?php

namespace App\Enums\Campagin;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case PENDING = 'pending';

    case ACTIVE = 'active';

    case COMPLETED = 'completed';

    case REJECTED = 'rejected';
}

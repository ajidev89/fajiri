<?php

namespace App\Enums\Poll;

use App\Http\Traits\EnumTrait;

enum PollStatus: string
{
    use EnumTrait;

    case DRAFT    = 'draft';
    case ACTIVE   = 'active';
    case INACTIVE = 'inactive';
}

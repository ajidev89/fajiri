<?php

namespace App\Enums\Disbursement;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case PENDING = 'pending';

    case COMPLETED = 'completed';

    case REJECTED = 'rejected';
}

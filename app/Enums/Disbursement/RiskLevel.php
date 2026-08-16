<?php

namespace App\Enums\Disbursement;

use App\Http\Traits\EnumTrait;

enum RiskLevel: string
{
    use EnumTrait;

    case LOW = 'low';

    case MEDIUM = 'medium';

    case HIGH = 'high';
}

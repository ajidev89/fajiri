<?php

namespace App\Enums\Kyc;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case NOT_STARTED = "not_started";
    case PENDING = "pending";
    case APPROVED = "approved";
    case RESUBMISSION = "resubmission";
    case DECLINED = "declined";
}
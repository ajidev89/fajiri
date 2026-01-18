<?php

namespace App\Enums\Verification;

use App\Http\Traits\EnumTrait;

enum Status: string
{
    use EnumTrait;

    case CREATED = "created";
    case IN_PROGRESS = "in_progress";
    case COMPLETED = "completed";
    case FAILED = "failed";
}
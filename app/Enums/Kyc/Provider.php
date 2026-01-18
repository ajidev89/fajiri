<?php

namespace App\Enums\Kyc;

use App\Http\Traits\EnumTrait;

enum Provider: string
{
    use EnumTrait;
    case VERIFF = "veriff";

}
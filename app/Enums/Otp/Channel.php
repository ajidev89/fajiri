<?php

namespace App\Enums\Otp;

use App\Http\Traits\EnumTrait;

enum Channel: string
{
    use EnumTrait;
    case EMAIL = 'email';
    case PHONE = 'phone';

}

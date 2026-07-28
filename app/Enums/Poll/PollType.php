<?php

namespace App\Enums\Poll;

use App\Http\Traits\EnumTrait;

enum PollType: string
{
    use EnumTrait;

    case RADIO      = 'radio';
    case CHECKBOX   = 'checkbox';
    case SHORT_TEXT = 'short_text';
    case LONG_TEXT  = 'long_text';
}

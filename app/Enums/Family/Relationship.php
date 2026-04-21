<?php

namespace App\Enums\Family;

use App\Http\Traits\EnumTrait;

enum Relationship: string
{
    use EnumTrait;

    case SON = 'son';
    case DAUGHTER = 'daughter';
    case PARTNER = 'partner';
    case SPOUSE = 'spouse';
    case FATHER = 'father';
    case MOTHER = 'mother';
}

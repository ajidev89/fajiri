<?php

namespace App\Enums\Profile;

use App\Http\Traits\EnumTrait;

enum Gender: string
{
    use EnumTrait;
    
    case RATHER_NOT_SAY = 'rather-not-say';
    case MALE = 'male';
    case FEMALE = 'femail';


}

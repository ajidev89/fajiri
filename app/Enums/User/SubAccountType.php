<?php

namespace App\Enums\User;

use App\Http\Traits\EnumTrait;

enum SubAccountType: string
{
    use EnumTrait;
    
    case GLOBAL_COLLABORATORS = 'global-collaborators';

    case GLOBAL_SPONSORS = 'global-sponsors';


}

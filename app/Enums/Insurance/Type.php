<?php

namespace App\Enums\Insurance;

use App\Http\Traits\EnumTrait;

enum Type: string
{
    use EnumTrait;
    
    case LIFE = 'life';

    case HEALTH = 'health';

    case PROPERTY = 'property';

    case AUTO = 'auto';

    case TRAVEL = 'travel';


}
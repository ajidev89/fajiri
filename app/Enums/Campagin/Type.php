<?php

namespace App\Enums\Campagin;

use App\Http\Traits\EnumTrait;

enum Type: string
{
    use EnumTrait;
    
    case DISASTER = 'disaster-relief';

    case EDUCATION = 'education';

    case MEDICAL = 'medical-aid';

    case HEALTH = 'health';

    case FOOD = 'food';

    case WATER = 'water';

    case SHELTER = 'shelter';

    case CLOTHING = 'clothing';

    case OTHER = 'other';


}
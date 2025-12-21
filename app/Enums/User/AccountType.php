<?php

namespace App\Enums\User;

use App\Http\Traits\EnumTrait;

enum AccountType: string
{
    use EnumTrait;
    
    case PERSONAL = 'personal';
    case BUSINESS = 'business';


}

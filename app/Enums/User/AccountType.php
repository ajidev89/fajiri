<?php

namespace App\Enums\User;

use App\Http\Traits\EnumTrait;

enum AccountType: string
{
    use EnumTrait;
    
    case IDENTIFIED_MEMBERSHIP = 'identified-membership';

    case PROJECT_MEMBERSHIP = 'project-membership';

    case CORPORATE_MEMBERSHIP = 'corporate-membership';


}

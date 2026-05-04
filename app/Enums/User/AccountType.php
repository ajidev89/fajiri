<?php

namespace App\Enums\User;

use App\Http\Traits\EnumTrait;

enum AccountType: string
{
    use EnumTrait;
    
    case IDENTIFIED_MEMBERSHIP = 'identified-membership';

    case PROJECT_MEMBERSHIP = 'project-membership';

    case CORPORATE_MEMBERSHIP = 'corporate-membership';

    public function label(): string
    {
        return match ($this) {
            self::IDENTIFIED_MEMBERSHIP => 'Fajiri Identified Membership',
            self::PROJECT_MEMBERSHIP => 'Fajiri Project Membership',
            self::CORPORATE_MEMBERSHIP => 'Fajiri Corporate Partners',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::IDENTIFIED_MEMBERSHIP => 'Support, Give, Receive or Share Gifts With Families',
            self::PROJECT_MEMBERSHIP => 'Raise Funds or Partner to Support Vulnerable Families',
            self::CORPORATE_MEMBERSHIP => 'Sponsor a Project or Collaborate for CSR Impact',
        };
    }


}

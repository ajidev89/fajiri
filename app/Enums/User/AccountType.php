<?php

namespace App\Enums\User;

use App\Http\Traits\EnumTrait;

enum AccountType: string
{
    use EnumTrait;
    
    case IDENTIFIED_MEMBERSHIP = 'identified-membership';

    case PROGRAM_MEMBERSHIP = 'program-membership';

    case CORPORATE_MEMBERSHIP = 'corporate-membership';

    public function label(): string
    {
        return match ($this) {
            self::IDENTIFIED_MEMBERSHIP => 'Fajiri Identified Membership',
            self::PROGRAM_MEMBERSHIP => 'Fajiri Program Membership',
            self::CORPORATE_MEMBERSHIP => 'Fajiri Corporate Partners',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::IDENTIFIED_MEMBERSHIP => 'Support, Give, Receive or Share Gifts With Families',
            self::PROGRAM_MEMBERSHIP => 'Raise Funds or Partner to Support Vulnerable Families',
            self::CORPORATE_MEMBERSHIP => 'Sponsor a Program or Collaborate for CSR Impact',
        };
    }


}

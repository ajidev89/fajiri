<?php

namespace App\Enums\Faq;

use App\Http\Traits\EnumTrait;

enum Type: string
{
    use EnumTrait;

    case GENERAL = 'general';
    case DONATIONS = 'donations';
    case MEMBERS = 'members';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::DONATIONS => 'Donations',
            self::MEMBERS => 'Members',
        };
    }
}

<?php

namespace App\Enums\Legal;

use App\Http\Traits\EnumTrait;

enum Type: string
{
    use EnumTrait;

    case TERMS = 'terms';
    case PRIVACY = 'privacy';

    public function label(): string
    {
        return match ($this) {
            self::TERMS => 'Terms of Service',
            self::PRIVACY => 'Privacy Policy',
        };
    }
}

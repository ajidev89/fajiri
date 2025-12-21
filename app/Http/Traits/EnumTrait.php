<?php

namespace App\Http\Traits;

/**
 * @property int|string $value
 */
trait EnumTrait
{
    public static function values(): array
    {
        return array_values(array_map(fn ($case) => $case->value, self::cases()));
    }
}

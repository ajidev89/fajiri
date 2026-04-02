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

    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->name] = $case->value;
        }

        return $array;
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'key' => $case->name,
            'value' => $case->value,
        ], self::cases());
    }

}

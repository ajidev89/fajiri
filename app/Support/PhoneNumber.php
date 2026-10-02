<?php

namespace App\Support;

class PhoneNumber
{
    public static function withCountryCode(?string $phone, ?string $phoneCode): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);

        if ($trimmed === '') {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '+') || str_starts_with($digits, '00')) {
            return '+'.ltrim($digits, '0');
        }

        $codeDigits = ltrim(preg_replace('/\D+/', '', (string) $phoneCode) ?? '', '0');

        if ($codeDigits === '') {
            return $trimmed;
        }

        if (str_starts_with($digits, $codeDigits)) {
            return '+'.$digits;
        }

        $national = ltrim($digits, '0');

        return '+'.$codeDigits.$national;
    }

    public static function isTestPhone(string $phone): bool
    {
        $candidates = self::variants($phone);

        foreach (config('otp.test_phones', []) as $testPhone) {
            if (array_intersect($candidates, self::variants((string) $testPhone)) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $trimmed = trim($phone);
        $digits = ltrim(preg_replace('/\D+/', '', $trimmed) ?? '', '0');

        return array_values(array_unique(array_filter([
            $trimmed,
            $digits !== '' ? $digits : null,
            $digits !== '' ? '+'.$digits : null,
        ])));
    }
}

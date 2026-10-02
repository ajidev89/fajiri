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
}

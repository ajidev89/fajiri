<?php

use Illuminate\Support\Facades\Crypt;

if (!function_exists('generateToken')) {
    function generateToken($value) {

        $data = [
            ...$value,
            'expiry' => now()->addMinutes(5)->timestamp
        ];
        return Crypt::encrypt($data);
    }
}



if (!function_exists('decryptToken')) {
    function decryptToken($token) {
        return Crypt::decrypt($token);
    }
}

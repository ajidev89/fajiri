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


if (!function_exists('imageToBase64')) {
    function imageToBase64(string $path): string
    {
        if (! file_exists($path)) {
            throw new RuntimeException('File not found');
        }

        $mime = mime_content_type($path);
        $data = base64_encode(file_get_contents($path));

        return "data:$mime;base64,$data";
    }
}




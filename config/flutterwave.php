<?php

return [
    'publicKey'     => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secretKey'     => env('FLUTTERWAVE_SECRET_KEY'),
    'secretHash'    => env('FLUTTERWAVE_SECRET_HASH'),
    'encryptionKey' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    'paymentUrl'    => env('FLUTTERWAVE_PAYMENT_URL', 'https://api.flutterwave.com/v3'),
];



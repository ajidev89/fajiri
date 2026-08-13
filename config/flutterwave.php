<?php

return [
    'publicKey' => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secretKey' => env('FLUTTERWAVE_SECRET_KEY'),
    'secretHash' => env('FLUTTERWAVE_SECRET_HASH'),
    'paymentUrl' => env('FLUTTERWAVE_PAYMENT_URL', 'https://api.flutterwave.com/v3'),
];

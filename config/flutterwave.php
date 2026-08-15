<?php

return [
    'clientId'     => env('FLUTTERWAVE_CLIENT_ID'),
    'clientSecret' => env('FLUTTERWAVE_CLIENT_SECRET'),
    'publicKey'    => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secretKey'    => env('FLUTTERWAVE_SECRET_KEY'),
    'secretHash'   => env('FLUTTERWAVE_SECRET_HASH'),
    'mode'         => env('FLUTTERWAVE_MODE', 'sandbox'),
    'version'      => env('FLUTTERWAVE_VERSION', 'v4'),
    'paymentUrl'   => env(
        'FLUTTERWAVE_PAYMENT_URL',
        env('FLUTTERWAVE_MODE', 'sandbox') === 'live'
            ? 'https://api.flutterwave.com'
            : 'https://developersandbox-api.flutterwave.com'
    ),
];


<?php

return [
    'clientId'     => env('FLUTTERWAVE_CLIENT_ID'),
    'clientSecret' => env('FLUTTERWAVE_CLIENT_SECRET'),
    'publicKey'    => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secretKey'    => env('FLUTTERWAVE_SECRET_KEY'),
    'secretHash'   => env('FLUTTERWAVE_SECRET_HASH'),
    'mode'         => env('FLUTTERWAVE_MODE', 'sandbox'),
    'version'      => env('FLUTTERWAVE_VERSION', 'v4'),
    'authUrl'      => env('FLUTTERWAVE_AUTH_URL', 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token'),
    'paymentUrl'   => env(
        'FLUTTERWAVE_PAYMENT_URL',
        env('FLUTTERWAVE_MODE', 'sandbox') === 'live'
            ? 'https://api.flutterwave.com'
            : 'https://developersandbox-api.flutterwave.com'
    ),
];


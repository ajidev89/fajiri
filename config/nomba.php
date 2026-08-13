<?php

return [
    'clientId' => env('NOMBA_CLIENT_ID'),
    'clientSecret' => env('NOMBA_CLIENT_SECRET'),
    'accountId' => env('NOMBA_ACCOUNT_ID'),
    'mode' => env('NOMBA_MODE', 'test'),
    'baseUrl' => env('NOMBA_MODE', 'test') === 'live'
        ? 'https://api.nomba.com/v1'
        : 'https://api.nomba.com/v1',
];

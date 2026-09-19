<?php

return [
    'default' => env('OTP_DEFAULT', '123456'),

    /*
    | When true, the default OTP is accepted for every identifier.
    | Test emails/phones always accept the default OTP.
    */
    'allow_default' => filter_var(env('OTP_ALLOW_DEFAULT', false), FILTER_VALIDATE_BOOL),

    'test_emails' => array_values(array_filter(array_map(
        'strtolower',
        array_map('trim', explode(',', env('OTP_TEST_EMAILS', 'wisdomzilla13@gmail.com')))
    ))),

    'test_phones' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('OTP_TEST_PHONES', '+2349063328998,+2349131461128,+12345678901'))
    ))),
];

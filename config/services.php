<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'smssid' => env('TWILIO_SMSSID')
    ],

    'veriff' => [
        'baseurl' => env('VERIFF_BASEURL'),
        'apiKey' => env('VERIFF_APIKEY'),
        'sigKey' => env('VERIFF_SIGKEY')
    ],
    
    'cloudinary' => [
        'cloud_name' =>env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET')
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'exchangerate_api' => [
        'key' => env('EXCHANGERATE_API_KEY'),
        'base_url' => 'https://v6.exchangerate-api.com/v6/',
    ],
    
    'revenuecat' => [
        'api_key' => env('REVENUE_CAT_API_KEY'),
        'project_id' => env('REVENUE_CAT_PROJECT_ID'),
        'webhook_key' => env('REVENUE_CAT_WEBHOOK_KEY'),
        'default_entitlement_id' => env('REVENUE_CAT_ENTITLEMENT_ID', 'premium'),
        'default_offering_id' => env('REVENUE_CAT_OFFERING_ID', 'default'),
        'app_id_ios' => env('REVENUE_CAT_APP_ID_IOS'),
        'app_id_android' => env('REVENUE_CAT_APP_ID_ANDROID'),
    ],


];

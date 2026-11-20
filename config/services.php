<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // Payzen/Izipay (Lyra). Pendiente de credenciales reales de Katherine;
    // ver app/Services/Payments/PayzenGateway.php y PAYMENTS_DRIVER en .env.
    'payzen' => [
        'shop_id' => env('PAYZEN_SHOP_ID'),
        'public_key' => env('PAYZEN_PUBLIC_KEY'),
        'api_password' => env('PAYZEN_API_PASSWORD'),
        'hmac_key_test' => env('PAYZEN_HMAC_KEY_TEST'),
        'hmac_key_prod' => env('PAYZEN_HMAC_KEY_PROD'),
        'mode' => env('PAYZEN_MODE', 'TEST'),
    ],

];

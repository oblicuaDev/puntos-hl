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

    // Sitio principal de la clínica (harkerlloreda.com), ajeno a este
    // proyecto. Se usa para enlazar "volver al sitio" desde el portal.
    'harker' => [
        'site_url' => env('HARKER_SITE_URL', 'https://harkerlloreda.com'),
    ],

    // Envío de SMS (códigos OTP de ingreso). SMS_DRIVER=log escribe el mensaje
    // en el log en vez de enviarlo (desarrollo/pruebas); smsto usa sms.to.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'smsto' => [
        'api_key' => env('SMSTO_API_KEY'),
        'sender_id' => env('SMSTO_SENDER_ID'),
        'base_url' => env('SMSTO_BASE_URL', 'https://api.sms.to'),
    ],

    // Carga diaria de facturación desde Google Sheets (HL-28). La hoja se
    // comparte como Editor con el client_email del JSON de la cuenta de
    // servicio. Sin fecha de corte la carga no procesa nada: las facturas
    // hasta esa fecha ya vienen en los saldos iniciales (HL-24).
    'billing_sheet' => [
        'credentials' => env('GOOGLE_SHEETS_CREDENTIALS', storage_path('app/private/google-service-account.json')),
        'spreadsheet_id' => env('BILLING_SHEET_ID'),
        'gid' => (int) env('BILLING_SHEET_GID', 0),
        'cutoff_date' => env('BILLING_SHEET_CUTOFF_DATE'),
    ],

    // Payzen/Izipay (Lyra). Ver app/Services/Payments/PayzenGateway.php y
    // PAYMENTS_DRIVER en .env.
    'payzen' => [
        'shop_id' => env('PAYZEN_SHOP_ID'),
        'public_key' => env('PAYZEN_PUBLIC_KEY'),
        'public_key_test' => env('PAYZEN_PUBLIC_KEY_TEST'),
        'api_password' => env('PAYZEN_API_PASSWORD'),
        'api_test_password' => env('PAYZEN_API_TEST_PASSWORD'),
        'hmac_key_test' => env('PAYZEN_HMAC_KEY_TEST'),
        'hmac_key_prod' => env('PAYZEN_HMAC_KEY_PROD'),
        'mode' => env('PAYZEN_MODE', 'TEST'),
    ],

];

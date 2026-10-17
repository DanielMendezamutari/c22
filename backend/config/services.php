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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        // Pausado por defecto hasta corregir la fórmula del informe (auditoría spec 005 §9); envío manual desde la web
        'informes_automaticos' => (bool) env('INFORMES_AUTOMATICOS', false),
    ],

    'conciliacion' => [
        'modo' => env('CONCILIACION_V2', 'off'),
        'cuentas_qr_oficiales' => array_values(array_filter(array_map('trim', explode(',', (string) env('CUENTAS_QR_OFICIALES', ''))))),
        'minutos_espera' => (int) env('CONCILIACION_MINUTOS_ESPERA', 10),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'whatsapp' => [
        'url' => rtrim((string) env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3000'), '/'),
        'key' => env('WHATSAPP_SERVICE_KEY', ''),
        'send_delay_min' => (int) env('WHATSAPP_SEND_DELAY_MIN', 6),
        'send_delay_max' => (int) env('WHATSAPP_SEND_DELAY_MAX', 14),
        'callback_url' => env('WHATSAPP_CALLBACK_URL', ''),
    ],

    /**
     * Reading the traffic the site already collects, rather than counting it a
     * second time on the server.
     */
    'ga4' => [
        'property_id' => env('GA4_PROPERTY_ID'),
        // Absolute path to the Google service-account key file. Kept outside
        // the repository: it is a credential, not configuration.
        'credentials' => env('GA4_CREDENTIALS_PATH'),
    ],

    'access_log' => [
        'path' => env('ACCESS_LOG_PATH', '/var/log/apache2/access.log'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

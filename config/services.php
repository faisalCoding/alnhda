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

    /**
     * The company's official accounts, declared once. They are published in
     * three different shapes — buttons in the footer and on the contact page,
     * `sameAs` for search engines, and a prose line for language models — and a
     * URL kept in three places is three URLs the moment one account moves.
     * Order here is the order the buttons appear in.
     */
    'social' => [
        'youtube' => [
            'label' => 'يوتيوب',
            'aria' => 'قناة اليوتيوب لشركة كيان النهضة العقارية',
            'url' => 'https://www.youtube.com/@KayanAlnhda',
        ],
        'instagram' => [
            'label' => 'إنستغرام',
            'aria' => 'حساب إنستغرام لشركة كيان النهضة العقارية',
            'url' => 'https://www.instagram.com/nahda_realestate/',
        ],
        'x' => [
            'label' => 'إكس',
            'aria' => 'حساب إكس لشركة كيان النهضة العقارية',
            'url' => 'https://x.com/Nahda_Cont',
            // يُستعمل في وسم twitter:site، وهو يطلبه مسبوقاً بعلامة @.
            'handle' => '@Nahda_Cont',
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

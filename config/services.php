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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'gate' => [
        'url' => env('GATE_URL'),
        'provisioning_client_id' => env('GATE_PROVISIONING_CLIENT_ID'),
        'provisioning_client_secret' => env('GATE_PROVISIONING_CLIENT_SECRET'),
        'sync_photo' => env('GATE_SYNC_PHOTO', false),
        'sync_qr' => env('GATE_SYNC_QR', false),
        'preview_ttl_minutes' => env('GATE_SYNC_PREVIEW_TTL', 30),
        'role_mapping' => [
            'super_admin' => 'super_admin', 'admin' => 'admin', 'bendahara' => 'bendahara',
            'kasir' => 'kasir', 'santri' => 'santri', 'wali' => 'wali',
        ],
    ],

];

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
        'sync_enabled' => env('GATE_SYNC_ENABLED', false),
        'sync_photo' => env('GATE_SYNC_PHOTO', false),
        'sync_qr' => env('GATE_SYNC_QR', false),
        'dry_run' => env('GATE_SYNC_DRY_RUN', true),
        'identity_bridge_enabled' => env('GATE_IDENTITY_BRIDGE_ENABLED', false),
        'timeout' => env('GATE_SYNC_TIMEOUT', 20),
        'connect_timeout' => env('GATE_SYNC_CONNECT_TIMEOUT', 5),
        'batch_size' => env('GATE_SYNC_BATCH_SIZE', 500),
        'photo_max_bytes' => env('GATE_SYNC_PHOTO_MAX_BYTES', 5242880),
        'photo_max_redirects' => env('GATE_SYNC_PHOTO_MAX_REDIRECTS', 2),
        'max_suspend_percent' => env('GATE_SYNC_MAX_SUSPEND_PERCENT', 10),
        'max_role_change_percent' => env('GATE_SYNC_MAX_ROLE_CHANGE_PERCENT', 10),
        'max_create_percent' => env('GATE_SYNC_MAX_CREATE_PERCENT', 25),
        'preview_ttl_minutes' => env('GATE_SYNC_PREVIEW_TTL', 30),
        'role_mapping' => [
            'student' => 'santri',
            'super_admin' => 'super_admin', 'admin' => 'admin', 'bendahara' => 'bendahara',
            'kasir' => 'kasir', 'santri' => 'santri', 'wali' => 'wali',
        ],
    ],

];

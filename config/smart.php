<?php

use App\Services\Payment\Providers\DokuProvider;
use App\Services\Payment\Providers\IpaymuProvider;
use App\Services\Payment\Providers\MidtransProvider;

return [
    'branding' => [
        'name' => 'SMART',
        'primary_color' => '#007A5C',
        'accent_color' => '#D4AF37',
        'tagline' => 'Sabira Mart Integrated Cashless System',
    ],

    'payments' => [
        'default_provider' => env('SMART_DEFAULT_PAYMENT_PROVIDER', 'ipaymu'),
        'provider_map' => [
            'ipaymu' => IpaymuProvider::class,
            'midtrans' => MidtransProvider::class,
            'doku' => DokuProvider::class,
        ],
        'providers' => [
            'ipaymu' => [
                'capabilities' => ['wallet_topup', 'pos_checkout', 'qris'],
                'mode' => env('IPAYMU_MODE', 'sandbox'),
                'credentials' => [
                    'virtual_account' => env('IPAYMU_VA'),
                    'api_key' => env('IPAYMU_API_KEY'),
                    'private_key' => env('IPAYMU_PRIVATE_KEY'),
                    'merchant_code' => env('IPAYMU_MERCHANT_CODE'),
                ],
                'endpoints' => [
                    'sandbox' => 'https://sandbox.ipaymu.com/api/v2',
                    'production' => 'https://app.ipaymu.com/api/v2',
                ],
                'callback_url' => env('IPAYMU_CALLBACK_URL'),
                'redirect_url' => env('IPAYMU_REDIRECT_URL'),
            ],
            'midtrans' => [
                'capabilities' => ['pos_checkout', 'subscription'],
                'mode' => env('MIDTRANS_MODE', 'sandbox'),
                'credentials' => [
                    'server_key' => env('MIDTRANS_SERVER_KEY'),
                    'client_key' => env('MIDTRANS_CLIENT_KEY'),
                ],
                'endpoints' => [
                    'sandbox' => 'https://api.sandbox.midtrans.com',
                    'production' => 'https://api.midtrans.com',
                    'snap_sandbox' => 'https://app.sandbox.midtrans.com',
                    'snap_production' => 'https://app.midtrans.com',
                ],
            ],
            'doku' => [
                'capabilities' => ['virtual_account', 'qris'],
                'mode' => env('DOKU_MODE', 'sandbox'),
                'credentials' => [
                    'client_id' => env('DOKU_CLIENT_ID'),
                    'secret_key' => env('DOKU_SECRET_KEY'),
                    'merchant_code' => env('DOKU_MERCHANT_CODE'),
                ],
                'endpoints' => [
                    'sandbox' => 'https://api-sandbox.doku.com',
                    'production' => 'https://api.doku.com',
                ],
                'callback_url' => env('DOKU_CALLBACK_URL'),
            ],
        ],
    ],

    'wallet' => [
        'default_daily_limit' => env('SMART_WALLET_DEFAULT_DAILY_LIMIT', 0),
        'default_weekly_limit' => env('SMART_WALLET_DEFAULT_WEEKLY_LIMIT', 200000),
        'default_monthly_limit' => env('SMART_WALLET_DEFAULT_MONTHLY_LIMIT', 0),
    ],

    'accounting' => [
        'accounts' => [
            'cash' => env('SMART_ACCOUNT_CASH', '101'),
            'wallet_liability' => env('SMART_ACCOUNT_WALLET_LIABILITY', '202'),
            'revenue' => env('SMART_ACCOUNT_REVENUE', '401'),
            'inventory' => env('SMART_ACCOUNT_INVENTORY', '103'),
            'cogs' => env('SMART_ACCOUNT_COGS', '501'),
        ],
    ],
];

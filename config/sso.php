<?php

return [
    'base_url' => rtrim(env('SSO_BASE_URL', 'https://gate.sabira-iibs.id'), '/'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI'),
    'scopes' => env('SSO_SCOPES', 'openid profile email roles'),
];

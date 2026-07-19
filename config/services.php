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

    'fasih' => [
        // Lifetime (in days) of a personal-access token minted by `fasih:issue-token`.
        'token_expiry_days' => (int) env('FASIH_TOKEN_EXPIRY_DAYS', 90),

        // Outbound notification adapter. `null` (the default) is a no-op — the HTTP driver
        // must be turned on explicitly in production. There is deliberately NO insecure default
        // secret and NO hardcoded endpoint: every endpoint URL and the signing secret come from
        // the environment only, so a misconfigured deployment sends nothing rather than posting
        // to a stale or wrong host.
        'driver' => env('FASIH_DRIVER', 'null'),
        'secret' => env('FASIH_WEBHOOK_SECRET'),
        'timeout' => (int) env('FASIH_TIMEOUT', 10),
        'connect_timeout' => (int) env('FASIH_CONNECT_TIMEOUT', 5),

        'lead_created' => [
            'enabled' => env('FASIH_LEAD_CREATED_ENABLED', true),
            'url' => env('FASIH_LEAD_CREATED_URL'),
        ],
        'affiliate_verified' => [
            'enabled' => env('FASIH_AFFILIATE_VERIFIED_ENABLED', true),
            'url' => env('FASIH_AFFILIATE_VERIFIED_URL'),
        ],
    ],

];

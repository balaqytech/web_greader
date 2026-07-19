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
    ],

    'webhooks' => [
        'secret' => env('WEBHOOK_SECRET', 'secret'),
        'lead' => [
            'enabled' => env('WEBHOOK_LEAD_ENABLED', true),
            'created_url' => env('WEBHOOK_LEAD_URL', 'https://www.uchat.com.au/api/iwh/fb149bff3f2f1dc61a752d37a527068e'),
        ],
        'affiliate' => [
            'enabled' => env('WEBHOOK_AFFILIATE_ENABLED', true),
            'verified_url' => env('WEBHOOK_AFFILIATE_VERIFIED_URL', 'https://www.uchat.com.au/api/iwh/464f76534bafb5f8a5904bd55f3747ae'),
        ],
    ],

];

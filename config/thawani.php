<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Thawani configuration — deliberately hand-written, NOT vendor:publish'd
|--------------------------------------------------------------------------
|
| `jkbroot/thawani` ships a config file with a REAL UAT secret key hardcoded as
| the default for `test.secret_key`, and its service provider calls
| `mergeConfigFrom()` — so those defaults apply even when the config was never
| published. Publishing it unchanged would commit someone else's credential to
| this repository; leaving it unpublished would silently transact against that
| stranger's UAT merchant whenever our own env var is missing, which looks
| exactly like success until you go looking for the money.
|
| This file exists to override that. `mergeConfigFrom()` merges the package's
| array UNDER the application's, and the merge is shallow — so declaring the
| `test` and `live` keys here replaces the package's versions wholesale, and
| every credential below resolves from the environment with no fallback.
|
| Secrets live only in the environment and are never committed. If a key is
| missing, `ThawaniGateway` refuses to make a request rather than guessing —
| see `ThawaniConfig`, which also rejects the package's bundled default key by
| value, in case this file is ever deleted and the package's defaults return.
|
*/

return [
    'mode' => env('THAWANI_MODE', 'test'),

    'test' => [
        'base_url' => env('THAWANI_TEST_BASE_URL', 'https://uatcheckout.thawani.om/api/v1'),
        'checkout_base_url' => env('THAWANI_TEST_CHECKOUT_BASE_URL', 'https://uatcheckout.thawani.om/pay'),
        'secret_key' => env('THAWANI_TEST_SECRET_KEY'),
        'publishable_key' => env('THAWANI_TEST_PUBLISHABLE_KEY'),
    ],

    'live' => [
        'base_url' => env('THAWANI_LIVE_BASE_URL', 'https://checkout.thawani.om/api/v1'),
        'checkout_base_url' => env('THAWANI_LIVE_CHECKOUT_BASE_URL', 'https://checkout.thawani.om/pay'),
        'secret_key' => env('THAWANI_LIVE_SECRET_KEY'),
        'publishable_key' => env('THAWANI_LIVE_PUBLISHABLE_KEY'),
    ],
];

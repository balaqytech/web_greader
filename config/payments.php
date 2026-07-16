<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default payment gateway driver
    |--------------------------------------------------------------------------
    |
    | Resolved by `App\Services\Payments\PaymentGatewayManager`. `thawani` talks
    | to the real provider through the application-owned adapter; `fake` is a
    | deterministic in-memory driver for tests.
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'thawani'),

    /*
    |--------------------------------------------------------------------------
    | Checkout session
    |--------------------------------------------------------------------------
    |
    | `product_name` is the line item the guardian sees on Thawani's hosted
    | checkout page.
    |
    */

    'checkout' => [
        'product_name' => env('PAYMENT_CHECKOUT_PRODUCT_NAME', 'Registration Fee'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | There is no webhook in this phase, so a payment completed after the
    | guardian closed the tab is settled by reconciliation instead of being
    | pushed to us. `stale_after_minutes` is how long a pending attempt must sit
    | untouched before reconciliation asks the provider what actually happened.
    |
    */

    'reconciliation' => [
        'stale_after_minutes' => (int) env('PAYMENT_RECONCILE_STALE_AFTER_MINUTES', 15),
    ],

];

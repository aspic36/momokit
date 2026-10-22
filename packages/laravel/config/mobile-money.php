<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default driver
    |--------------------------------------------------------------------------
    |
    | The driver used when none is passed explicitly. The "mock" driver is a
    | local simulator: it needs no credentials, no network and no real money.
    |
    */

    'default' => env('MOBILE_MONEY_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    |
    | Real aggregators are registered only when their credentials are set,
    | so a fresh install always works with the mock driver.
    |
    */

    'drivers' => [

        'mock' => [
            'driver' => 'mock',
        ],

        'fedapay' => [
            'driver' => 'fedapay',
            'secret_key' => env('FEDAPAY_SECRET_KEY'),
            'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
            'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
            'callback_url' => env('FEDAPAY_CALLBACK_URL'),
        ],

    ],

];

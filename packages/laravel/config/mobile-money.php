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
    | Real aggregators are added here as they become available. The mock
    | driver is always available.
    |
    */

    'drivers' => [

        'mock' => [
            'driver' => 'mock',
        ],

        // 'fedapay' => [
        //     'driver' => 'fedapay',
        //     'secret' => env('FEDAPAY_SECRET_KEY'),
        //     'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
        // ],

    ],

];

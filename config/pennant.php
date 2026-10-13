<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pennant Store
    |--------------------------------------------------------------------------
    |
    | Here you will specify the default store that Pennant should use when
    | storing and resolving feature flag values. Pennant ships with the
    | ability to store flag values in an in-memory array or database.
    |
    | Supported: "array", "database"
    |
    */

    // ItsMint features are entitlements derived from the subscription state
    // (see AppServiceProvider::defineFeatures). The "database" store would persist
    // the FIRST resolved value forever: a free user would stay locked out after
    // paying, a premium user would keep features after cancelling. The "array"
    // store resolves them on every request (in-memory, per request), which is
    // cheap and always reflects the current subscription.
    'default' => env('PENNANT_STORE', 'array'),

    /*
    |--------------------------------------------------------------------------
    | Pennant Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure each of the stores that should be available to
    | Pennant. These stores shall be used to store resolved feature flag
    | values - you may configure as many as your application requires.
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'features',
        ],

    ],
];

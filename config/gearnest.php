<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GearNest Delivery & Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration parameters for delivery fee calculation, maximum radius,
    | stock alerts, and Midtrans payment settings.
    |
    */

    'delivery' => [
        'base_fee' => (float) env('DELIVERY_BASE_FEE', 10000),
        'per_km_fee' => (float) env('DELIVERY_PER_KM_FEE', 3000),
        'max_distance_km' => (int) env('DELIVERY_MAX_DISTANCE_KM', 50),
    ],

    'stock' => [
        'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 3),
    ],
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kembara.id Service Configuration
    |--------------------------------------------------------------------------
    |
    | Delivery-fee parameters are stored in the DB (settings table, editable at
    | /admin/settings) and read via DeliveryFeeService — not here.
    |
    */

    'stock' => [
        'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 3),
    ],
];

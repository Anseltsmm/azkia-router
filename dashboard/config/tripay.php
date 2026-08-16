<?php

return [
    'mode' => env('TRIPAY_MODE', 'sandbox'),
    'api_key' => env('TRIPAY_API_KEY'),
    'private_key' => env('TRIPAY_PRIVATE_KEY'),
    'merchant_code' => env('TRIPAY_MERCHANT_CODE'),
    'minimum_topup' => (int) env('TRIPAY_MINIMUM_TOPUP', 10000),
    'expiry_hours' => (int) env('TRIPAY_EXPIRY_HOURS', 24),
    'base_url' => env('TRIPAY_MODE', 'sandbox') === 'production'
        ? 'https://tripay.co.id/api'
        : 'https://tripay.co.id/api-sandbox',
];

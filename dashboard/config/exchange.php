<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kurs USD -> IDR Realtime
    |--------------------------------------------------------------------------
    |
    | Harga & saldo disimpan dalam USD (mengikuti harga upstream AI).
    | Kurs realtime dipakai untuk menampilkan nilai dalam Rupiah dan
    | mengonversi nominal topup dari IDR ke USD.
    |
    | Sumber default: open.er-api.com (gratis, tanpa API key, update harian).
    |
    */

    'rate_api_url' => env('EXCHANGE_RATE_API_URL', 'https://open.er-api.com/v6/latest/USD'),

    // Kurs cadangan dipakai jika API tidak dapat diakses dan belum ada nilai cache.
    'fallback_rate' => env('EXCHANGE_RATE_FALLBACK', 16000),

    // Lama kurs disimpan di cache (detik). Dikoreksi tiap 30 detik oleh timer
    // azkia-exchange-rate.timer; on-demand juga refresh bila cache expire.
    'cache_ttl_seconds' => env('EXCHANGE_RATE_CACHE_TTL', 30),

];

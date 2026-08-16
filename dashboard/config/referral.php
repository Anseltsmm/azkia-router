<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Program Referral
    |--------------------------------------------------------------------------
    |
    | Reward flat diberikan kepada referrer setiap kali teman yang direferensikan
    | melakukan top-up pertama dengan nominal minimal tertentu (dalam IDR).
    |
    */

    // Reward saldo (USD) untuk referrer per teman yang aktif.
    'reward_usd' => (float) env('REFERRAL_REWARD_USD', 0.50),

    // Minimum nominal top-up pertama teman (IDR) agar reward cair.
    'min_topup_idr' => (int) env('REFERRAL_MIN_TOPUP_IDR', 50000),
];

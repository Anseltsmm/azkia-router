<?php

return [
    'required' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'string' => ':attribute harus berupa teks.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'min' => ['string' => ':attribute minimal :min karakter.', 'numeric' => ':attribute minimal :min.'],
    'max' => ['string' => ':attribute maksimal :max karakter.', 'numeric' => ':attribute maksimal :max.'],
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'unique' => ':attribute sudah digunakan.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'after' => ':attribute harus setelah :date.',
    'in' => ':attribute yang dipilih tidak valid.',
    'regex' => 'Format :attribute tidak valid.',
    'attributes' => ['name' => 'nama', 'email' => 'email', 'password' => 'password', 'current_password' => 'password saat ini', 'monthly_quota_tokens' => 'kuota bulanan', 'expires_at' => 'tanggal kedaluwarsa', 'amount' => 'nominal', 'method' => 'metode pembayaran', 'customer_phone' => 'nomor telepon', 'locale' => 'bahasa'],
];

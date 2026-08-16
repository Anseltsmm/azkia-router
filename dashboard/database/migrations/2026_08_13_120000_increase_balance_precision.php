<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Saldo user dinaikkan presisinya dari 2 -> 6 desimal.
     *
     * Biaya PAYG per request bernilai mikro (mis. $0.000046 untuk beberapa
     * ribu token), sehingga kolom 2 desimal membulatkan pemotongan menjadi 0
     * dan pemakaian kecil tidak terpotong dari saldo. Disamakan dengan
     * presisi usage_logs.cost (decimal 14,6).
     *
     * ALTER TYPE hanya didukung PostgreSQL; driver lain (SQLite untuk test)
     * dilewati karena tidak mengubah tipe kolom.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN balance TYPE numeric(14, 6)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN balance TYPE numeric(14, 2)');
        }
    }
};

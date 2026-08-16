<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Presisi amount & balance_after transactions dinaikkan dari 2 -> 6 desimal,
     * menyamai users.balance & usage_logs.cost (numeric 14,6). Debit pemakaian
     * PAYG bernilai mikro (mis. $0.000046) tidak boleh terbulatkan jadi 0 di ledger.
     *
     * ALTER TYPE hanya didukung PostgreSQL; driver lain (SQLite untuk test)
     * dilewati karena tidak mengubah tipe kolom.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions ALTER COLUMN amount TYPE numeric(14, 6)');
            DB::statement('ALTER TABLE transactions ALTER COLUMN balance_after TYPE numeric(14, 6)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions ALTER COLUMN amount TYPE numeric(14, 2)');
            DB::statement('ALTER TABLE transactions ALTER COLUMN balance_after TYPE numeric(14, 2)');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralat: kembali ke USD sebagai mata uang simpanan.
     * Harga upstream AI selalu dalam USD; kurs USD->IDR realtime
     * (open.er-api.com) dipakai saat menampilkan & melakukan topup.
     *
     * Nilai dibagi 16.000 — invers dari migration convert_currency_to_idr
     * yang sebelumnya mengalikan dengan kurs tetap tersebut.
     */
    public function up(): void
    {
        $rate = 16000;

        // Harga per 1M token (pricing_rules) -> USD
        DB::table('pricing_rules')->update([
            'input_per_million' => DB::raw("input_per_million / {$rate}"),
            'output_per_million' => DB::raw("output_per_million / {$rate}"),
            'currency' => 'USD',
        ]);
        $this->setCurrencyDefault('pricing_rules', 'USD');

        // Saldo user -> USD
        DB::table('users')->update([
            'balance' => DB::raw("balance / {$rate}"),
        ]);

        // Riwayat transaksi -> USD
        DB::table('transactions')->update([
            'amount' => DB::raw("amount / {$rate}"),
            'balance_after' => DB::raw("balance_after / {$rate}"),
            'currency' => 'USD',
        ]);
        $this->setCurrencyDefault('transactions', 'USD');

        // Biaya pemakaian historis -> USD
        DB::table('usage_logs')->update([
            'cost' => DB::raw("cost / {$rate}"),
        ]);
    }

    /**
     * ALTER COLUMN ... SET DEFAULT hanya didukung PostgreSQL; dilewati pada
     * driver lain (mis. SQLite untuk test) agar migration portabel.
     */
    private function setCurrencyDefault(string $table, string $currency): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement("ALTER TABLE {$table} ALTER COLUMN currency SET DEFAULT '{$currency}'");
    }

    public function down(): void
    {
        $rate = 16000;

        DB::table('pricing_rules')->update([
            'input_per_million' => DB::raw("input_per_million * {$rate}"),
            'output_per_million' => DB::raw("output_per_million * {$rate}"),
            'currency' => 'IDR',
        ]);
        $this->setCurrencyDefault('pricing_rules', 'IDR');

        DB::table('users')->update([
            'balance' => DB::raw("balance * {$rate}"),
        ]);

        DB::table('transactions')->update([
            'amount' => DB::raw("amount * {$rate}"),
            'balance_after' => DB::raw("balance_after * {$rate}"),
            'currency' => 'IDR',
        ]);
        $this->setCurrencyDefault('transactions', 'IDR');

        DB::table('usage_logs')->update([
            'cost' => DB::raw("cost * {$rate}"),
        ]);
    }
};

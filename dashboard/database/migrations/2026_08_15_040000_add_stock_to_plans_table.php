<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stok penjualan plan: null = tanpa batas, N = jumlah yang bisa dibeli.
        // Berkurang setiap kali user membeli plan.
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('stock')->nullable()->after('resets_daily');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};

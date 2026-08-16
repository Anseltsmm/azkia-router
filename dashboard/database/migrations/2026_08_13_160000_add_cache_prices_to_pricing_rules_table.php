<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga cache per 1 juta token (USD), nullable.
     * - cache_read_input_per_million : harga input saat token dilayani dari cache
     *   provider (prompt caching). Null = tanpa diskon (pakai input_per_million).
     * - cache_write_per_million      : harga per token yang ditulis ke cache
     *   provider (cache creation). Null = tanpa biaya cache write.
     */
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->decimal('cache_read_input_per_million', 12, 4)->nullable()->after('output_per_million');
            $table->decimal('cache_write_per_million', 12, 4)->nullable()->after('cache_read_input_per_million');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['cache_read_input_per_million', 'cache_write_per_million']);
        });
    }
};

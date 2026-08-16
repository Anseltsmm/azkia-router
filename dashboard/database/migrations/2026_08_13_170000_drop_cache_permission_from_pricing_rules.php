<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus izin cache per-model (cache_read / cache_write) dari pricing_rules.
     * Cache Redis gateway kini selalu aktif (dikontrol global settings.cache_enabled);
     * hanya harga cache (read/write per 1M) yang tersimpan di pricing_rules.
     */
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['cache_read', 'cache_write']);
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->boolean('cache_read')->default(true)->after('is_active');
            $table->boolean('cache_write')->default(true)->after('cache_read');
        });
    }
};

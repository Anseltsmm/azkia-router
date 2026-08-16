<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kontrol cache per model di pricing rule:
     * cache_read  = request boleh dilayani dari cache (hit)
     * cache_write = respons boleh disimpan ke cache (miss)
     * Default true agar perilaku lama (cache aktif) tetap sama.
     */
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->boolean('cache_read')->default(true)->after('is_active');
            $table->boolean('cache_write')->default(true)->after('cache_read');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['cache_read', 'cache_write']);
        });
    }
};

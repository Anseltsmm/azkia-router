<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit cache: cache_read = request dilayani dari cache (hit),
     * cache_write = respons disimpan ke cache (miss yang di-cache).
     */
    public function up(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->boolean('cache_read')->default(false)->after('user_agent');
            $table->boolean('cache_write')->default(false)->after('cache_read');
        });
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn(['cache_read', 'cache_write']);
        });
    }
};

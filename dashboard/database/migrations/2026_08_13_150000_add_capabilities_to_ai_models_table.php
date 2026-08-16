<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kemampuan multi-modal per model (array JSON), mis. ["chat", "embedding"].
     * Kolom type tetap ada sebagai tipe utama untuk ikon & kompatibilitas lama.
     */
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('capabilities');
        });
    }
};

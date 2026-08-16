<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ikon model yang di-upload admin (path relatif di storage/app/public/model-icons).
     * Null = tidak ada ikon, tampilan memakai ikon SVG default berdasarkan tipe.
     */
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->string('icon_path')->nullable()->after('capabilities');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};

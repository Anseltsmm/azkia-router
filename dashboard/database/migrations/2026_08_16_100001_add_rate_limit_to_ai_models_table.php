<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batas request per menit per API key per model (ditegakkan gateway).
        // Null = tanpa batas; N = maksimal N request/menit untuk model ini.
        Schema::table('ai_models', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_limit_per_minute')->nullable()->after('context_window');
        });
    }

    public function down(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            $table->dropColumn('rate_limit_per_minute');
        });
    }
};

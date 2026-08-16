<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batas request per menit per user per plan (ditegakkan gateway).
        // Null = tanpa batas; N = maksimal N request/menit yang memakai kuota plan ini.
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_limit_per_minute')->nullable()->after('daily_limit_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('rate_limit_per_minute');
        });
    }
};

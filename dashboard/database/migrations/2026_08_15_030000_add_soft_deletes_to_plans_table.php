<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan dihapus secara soft agar user_plans (kuota yang sudah dibeli)
        // tetap utuh dan bisa dipakai sampai kedaluwarsa.
        Schema::table('plans', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

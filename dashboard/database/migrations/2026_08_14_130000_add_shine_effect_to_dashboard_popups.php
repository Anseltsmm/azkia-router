<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_popups', function (Blueprint $table) {
            $table->boolean('has_shine_effect')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_popups', function (Blueprint $table) {
            $table->dropColumn('has_shine_effect');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('bonus_idr')->default(0)->after('amount_idr');
            $table->decimal('bonus_usd', 18, 6)->default(0)->after('bonus_idr');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn(['bonus_idr', 'bonus_usd']);
        });
    }
};

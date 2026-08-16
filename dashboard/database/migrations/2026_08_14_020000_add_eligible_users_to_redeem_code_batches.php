<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redeem_code_batches', function (Blueprint $table) {
            $table->string('eligible_users')->default('all')->index();
        });
    }

    public function down(): void
    {
        Schema::table('redeem_code_batches', function (Blueprint $table) {
            $table->dropColumn('eligible_users');
        });
    }
};

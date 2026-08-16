<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('mode')->default('sandbox');
            $table->text('api_key_encrypted')->nullable();
            $table->text('private_key_encrypted')->nullable();
            $table->text('merchant_code_encrypted')->nullable();
            $table->unsignedBigInteger('minimum_topup')->default(10000);
            $table->unsignedInteger('expiry_hours')->default(24);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paket kuota token (bundle) selain PAYG. Contoh: 15M token/hari USD 0.70,
        // atau 100M token/minggu dengan batas harian 15M.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('total_tokens');
            $table->unsignedBigInteger('daily_limit_tokens')->nullable();
            $table->unsignedInteger('duration_hours');
            $table->decimal('price_usd', 14, 6);
            // Pasangan harga IDR untuk tampilan (opsional); pembayaran tetap saldo USD.
            $table->decimal('price_idr', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Kuota plan yang dibeli user. Semua API key milik user berbagi kuota ini.
        Schema::create('user_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tokens_remaining');
            $table->unsignedBigInteger('daily_limit_tokens')->nullable();
            $table->unsignedBigInteger('daily_tokens_used')->default(0);
            $table->date('daily_reset_date')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('purchased_at');
            $table->string('status')->default('active'); // active | consumed | expired
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'expires_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Toggle PAYG: bila nonaktif, pemakaian hanya dari kuota plan.
            $table->boolean('payg_enabled')->default(true);
        });

        // Kolom pelacak pemakaian plan di sisi gateway (billing_events diisi
        // langsung oleh gateway; usage_logs dicatat saat settlement).
        Schema::table('billing_events', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_tokens')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
        });

        Schema::table('usage_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_tokens')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn(['plan_tokens', 'plan_id']);
        });

        Schema::table('billing_events', function (Blueprint $table) {
            $table->dropColumn(['plan_tokens', 'plan_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('payg_enabled');
        });

        Schema::dropIfExists('user_plans');
        Schema::dropIfExists('plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_ref')->unique();
            $table->string('tripay_reference')->nullable()->unique();
            $table->string('payment_method');
            $table->string('payment_name')->nullable();
            $table->unsignedBigInteger('amount_idr');
            $table->decimal('credit_usd', 14, 6);
            $table->decimal('exchange_rate', 14, 4);
            $table->unsignedBigInteger('fee_customer')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('status')->default('UNPAID')->index();
            $table->string('pay_code')->nullable();
            $table->text('pay_url')->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->json('tripay_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};

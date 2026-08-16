<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redeem_code_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('generation_idempotency')->unique();
            $table->string('label')->nullable();
            $table->decimal('amount', 14, 6);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('max_total_uses');
            $table->unsignedInteger('max_uses_per_account');
            $table->unsignedInteger('max_uses_per_ip');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('redeem_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('redeem_code_batches')->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->string('code_hint', 12);
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['batch_id', 'is_active']);
        });

        Schema::create('redeem_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('redeem_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('request_idempotency')->unique();
            $table->char('ip_hash', 64);
            $table->decimal('amount', 14, 6);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['redeem_code_id', 'user_id']);
            $table->index(['redeem_code_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redeem_code_redemptions');
        Schema::dropIfExists('redeem_codes');
        Schema::dropIfExists('redeem_code_batches');
    }
};

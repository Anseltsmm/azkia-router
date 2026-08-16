<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('model');
            $table->string('endpoint');
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->decimal('cost', 14, 6)->nullable();
            $table->string('upstream_request_id')->nullable();
            $table->string('usage_source')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('usage_logs', function (Blueprint $table) {
            $table->uuid('billing_id')->nullable()->unique();
            $table->string('upstream_request_id')->nullable()->index();
            $table->string('usage_source')->default('upstream');
            $table->foreign('billing_id')->references('id')->on('billing_events')->restrictOnDelete();
            $table->index(['api_key_id', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('billing_id')->nullable()->unique();
            $table->decimal('balance_before', 14, 6)->nullable();
            $table->foreign('billing_id')->references('id')->on('billing_events')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['billing_id']);
            $table->dropColumn(['billing_id', 'balance_before']);
        });
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropForeign(['billing_id']);
            $table->dropIndex(['api_key_id', 'created_at']);
            $table->dropColumn(['billing_id', 'upstream_request_id', 'usage_source']);
        });
        Schema::dropIfExists('billing_events');
    }
};

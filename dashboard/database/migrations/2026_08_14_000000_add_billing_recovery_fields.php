<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_events', function (Blueprint $table) {
            $table->jsonb('payload')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropColumn(['payload', 'retry_count', 'next_retry_at', 'last_attempt_at', 'last_error']);
        });
    }
};

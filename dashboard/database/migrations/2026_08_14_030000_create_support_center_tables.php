<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 24)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 180);
            $table->string('category', 32);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 32)->default('awaiting_support');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_reference')->nullable();
            $table->uuid('billing_event_id')->nullable();
            $table->foreignId('payment_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_user_message_at')->nullable();
            $table->timestamp('last_admin_message_at')->nullable();
            $table->timestamp('last_user_read_at')->nullable();
            $table->timestamp('last_admin_read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->foreign('billing_event_id')->references('id')->on('billing_events')->nullOnDelete();
            $table->index(['status', 'last_message_at']);
            $table->index(['assigned_admin_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['category', 'priority']);
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('sender_role', 16);
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};

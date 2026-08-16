<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('reserved_balance', 14, 6)->default(0);
        });

        Schema::create('api_key_monthly_usage', function (Blueprint $table) {
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->unsignedBigInteger('settled_tokens')->default(0);
            $table->unsignedBigInteger('reserved_tokens')->default(0);
            $table->timestamps();
            $table->primary(['api_key_id', 'period_start']);
        });

        Schema::table('billing_events', function (Blueprint $table) {
            $table->decimal('reserved_cost', 14, 6)->default(0);
            $table->unsignedBigInteger('reserved_tokens')->default(0);
            $table->date('quota_period_start')->nullable();
            $table->jsonb('pricing_snapshot')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('upstream_started_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('settlement_kind')->nullable();
            $table->string('usage_quality')->nullable();
            $table->text('stream_failure_reason')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                insert into api_key_monthly_usage (api_key_id, period_start, settled_tokens, reserved_tokens, created_at, updated_at)
                select api_key_id, date_trunc('month', created_at)::date,
                       sum(coalesce(input_tokens, 0) + coalesce(output_tokens, 0))::bigint, 0, now(), now()
                from usage_logs
                where api_key_id is not null
                group by api_key_id, date_trunc('month', created_at)::date
                on conflict (api_key_id, period_start) do update
                set settled_tokens = excluded.settled_tokens, updated_at = now()
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('billing_events', function (Blueprint $table) {
            $table->dropColumn([
                'reserved_cost', 'reserved_tokens', 'quota_period_start', 'pricing_snapshot',
                'reserved_at', 'upstream_started_at', 'released_at', 'settlement_kind',
                'usage_quality', 'stream_failure_reason',
            ]);
        });
        Schema::dropIfExists('api_key_monthly_usage');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reserved_balance');
        });
    }
};

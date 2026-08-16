<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plan gratis bawaan: tanpa masa berlaku, kuota reset tiap hari.
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('resets_daily')->default(false)->after('is_active');
            $table->unsignedInteger('duration_hours')->nullable()->change();
        });

        Schema::table('user_plans', function (Blueprint $table) {
            $table->boolean('resets_daily')->default(false)->after('status');
            $table->timestamp('expires_at')->nullable()->change();
        });

        // Satu plan gratis per user (hanya berlaku untuk plan reset harian).
        Schema::table('user_plans', function (Blueprint $table) {
            $table->unique(['user_id', 'plan_id'], 'user_plans_one_free_per_user')->where('resets_daily');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::table('plans')->insert([
                'name' => 'Free Harian',
                'slug' => 'free-daily',
                'total_tokens' => 7000000,
                'daily_limit_tokens' => 7000000,
                'duration_hours' => null,
                'price_usd' => 0,
                'price_idr' => 0,
                'is_active' => true,
                'resets_daily' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Berikan plan gratis ke semua user yang sudah ada (idempotent).
            DB::statement(<<<'SQL'
                insert into user_plans (user_id, plan_id, tokens_remaining, daily_limit_tokens, daily_tokens_used, daily_reset_date, expires_at, purchased_at, status, resets_daily, created_at, updated_at)
                select u.id, p.id, p.total_tokens, coalesce(p.daily_limit_tokens, p.total_tokens), 0, null, null, now(), 'active', true, now(), now()
                from users u
                cross join plans p
                where p.slug = 'free-daily'
                  and not exists (select 1 from user_plans up where up.user_id = u.id and up.plan_id = p.id)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('user_plans', function (Blueprint $table) {
            $table->dropUnique('user_plans_one_free_per_user');
            $table->dropColumn('resets_daily');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('resets_daily');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::table('user_plans')->whereIn('plan_id', DB::table('plans')->where('slug', 'free-daily')->pluck('id'))->delete();
            DB::table('plans')->where('slug', 'free-daily')->delete();
        }
    }
};

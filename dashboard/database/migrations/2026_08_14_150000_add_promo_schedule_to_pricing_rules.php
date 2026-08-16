<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->timestamp('promo_starts_at')->nullable()->after('is_promo');
            $table->timestamp('promo_ends_at')->nullable()->after('promo_starts_at');
            $table->index(['is_promo', 'promo_starts_at', 'promo_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropIndex(['is_promo', 'promo_starts_at', 'promo_ends_at']);
            $table->dropColumn(['promo_starts_at', 'promo_ends_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->decimal('original_input_per_million', 18, 6)->nullable()->after('output_per_million');
            $table->decimal('original_output_per_million', 18, 6)->nullable()->after('original_input_per_million');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['original_input_per_million', 'original_output_per_million']);
        });
    }
};

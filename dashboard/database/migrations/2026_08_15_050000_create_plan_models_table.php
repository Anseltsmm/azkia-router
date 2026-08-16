<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Model yang boleh memakai kuota sebuah plan. Plan TANPA baris di tabel
        // ini mencakup SEMUA model (perilaku default). Gateway memfilter alokasi
        // kuota plan berdasarkan ai_model_id request.
        Schema::create('plan_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_id')->constrained()->cascadeOnDelete();
            $table->unique(['plan_id', 'ai_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_models');
    }
};

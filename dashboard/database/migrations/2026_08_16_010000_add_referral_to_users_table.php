<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('status');
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete()->after('referral_code');
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by');
        });

        // Backfill kode referral untuk user lama agar link referral langsung bisa dipakai.
        foreach (User::whereNull('referral_code')->cursor() as $user) {
            $user->referral_code = User::generateReferralCode();
            $user->save();
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn(['referral_code', 'referral_rewarded_at']);
        });
    }
};

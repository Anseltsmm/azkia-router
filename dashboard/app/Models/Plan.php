<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'total_tokens', 'daily_limit_tokens', 'duration_hours', 'price_usd', 'price_idr', 'is_active', 'resets_daily', 'stock', 'rate_limit_per_minute'])]
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'resets_daily' => 'boolean',
            'total_tokens' => 'integer',
            'daily_limit_tokens' => 'integer',
            'duration_hours' => 'integer',
            'price_usd' => 'decimal:6',
            'price_idr' => 'decimal:2',
            'stock' => 'integer',
            'rate_limit_per_minute' => 'integer',
        ];
    }

    /**
     * Label stok penjualan: "Tanpa batas" bila null, jumlah bila terbatas.
     */
    public function getStockLabelAttribute(): string
    {
        return $this->stock === null ? 'Tanpa batas' : number_format($this->stock, 0, ',', '.');
    }

    /**
     * Stok habis: hanya relevan untuk plan dengan stok terbatas (non-null).
     */
    public function getIsSoldOutAttribute(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }

    public function userPlans()
    {
        return $this->hasMany(UserPlan::class);
    }

    /**
     * Model yang boleh memakai kuota plan ini. Kosong = semua model.
     */
    public function models()
    {
        return $this->belongsToMany(AiModel::class, 'plan_models');
    }

    /**
     * Pilihan durasi (jam => label) untuk dropdown form admin.
     * Nilai kunci "" = tanpa masa berlaku (tidak kedaluwarsa).
     */
    public static function durationOptions(): array
    {
        return [
            '' => 'Tanpa masa berlaku (tidak expired)',
            12 => '12 jam',
            24 => '1 hari',
            48 => '2 hari',
            72 => '3 hari',
            168 => '7 hari',
            336 => '14 hari',
            720 => '30 hari',
            2160 => '90 hari',
            8760 => '1 tahun',
        ];
    }

    /**
     * Total token dalam notasi ringkas (mis. "15M", "100M").
     */
    public function getTokensLabelAttribute(): string
    {
        return format_compact_number($this->total_tokens);
    }

    public function getDailyLimitLabelAttribute(): ?string
    {
        return $this->daily_limit_tokens ? format_compact_number($this->daily_limit_tokens).'/hari' : null;
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->resets_daily || $this->duration_hours === null) {
            return 'Tanpa masa berlaku';
        }

        $hours = $this->duration_hours;
        if ($hours % 24 === 0) {
            $days = $hours / 24;

            return $days == 1 ? '1 hari' : $days.' hari';
        }

        return $hours.' jam';
    }

    /**
     * Berikan plan gratis (resets_daily) ke user — idempotent, satu per user.
     */
    public static function grantFreePlan(User $user): ?UserPlan
    {
        $plan = static::query()->where('resets_daily', true)->where('is_active', true)->first();
        if (! $plan) {
            return null;
        }

        return UserPlan::firstOrCreate(
            ['user_id' => $user->id, 'plan_id' => $plan->id],
            [
                'tokens_remaining' => $plan->total_tokens,
                'daily_limit_tokens' => $plan->daily_limit_tokens ?? $plan->total_tokens,
                'daily_tokens_used' => 0,
                'expires_at' => null,
                'purchased_at' => now(),
                'status' => 'active',
                'resets_daily' => true,
            ]
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'plan_id', 'tokens_remaining', 'daily_limit_tokens', 'daily_tokens_used', 'daily_reset_date', 'expires_at', 'purchased_at', 'status', 'resets_daily'])]
class UserPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tokens_remaining' => 'integer',
            'daily_limit_tokens' => 'integer',
            'daily_tokens_used' => 'integer',
            'daily_reset_date' => 'date',
            'expires_at' => 'datetime',
            'purchased_at' => 'datetime',
            'resets_daily' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        // Plan yang dihapus (soft delete) tetap ditampilkan di riwayat user
        // karena kuota yang sudah dibeli tetap berlaku sampai kedaluwarsa.
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /**
     * Plan yang masih bisa dipakai: status aktif dan (belum kedaluwarsa atau tanpa masa berlaku).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Sisa kuota yang bisa dipakai sekarang (mengikuti aturan reset harian gateway).
     */
    public function getRemainingTokensAttribute(): int
    {
        if ($this->resets_daily && $this->daily_limit_tokens !== null) {
            return max(0, $this->daily_limit_tokens - $this->dailyTokensUsedAfterReset());
        }

        return max(0, (int) $this->tokens_remaining);
    }

    /**
     * Token yang sudah terpakai (untuk plan reset harian = pemakaian hari ini).
     */
    public function getTokensUsedAttribute(): int
    {
        if ($this->resets_daily && $this->daily_limit_tokens !== null) {
            return min($this->dailyTokensUsedAfterReset(), (int) $this->daily_limit_tokens);
        }

        return max(0, (int) ($this->plan?->total_tokens ?? 0) - (int) $this->tokens_remaining);
    }

    /**
     * daily_tokens_used setelah reset harian (reset mengikuti tanggal server, sama seperti gateway).
     */
    private function dailyTokensUsedAfterReset(): int
    {
        if ($this->daily_reset_date === null || $this->daily_reset_date->isBefore(now()->toDateString())) {
            return 0;
        }

        return (int) $this->daily_tokens_used;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status !== 'active' || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Total kuota yang menjadi acuan progress bar: batas harian untuk plan
     * reset harian, total token plan untuk plan biasa.
     */
    public function getQuotaTokensAttribute(): int
    {
        if ($this->resets_daily && $this->daily_limit_tokens !== null) {
            return (int) $this->daily_limit_tokens;
        }

        return (int) ($this->plan?->total_tokens ?? 0);
    }

    /**
     * Persentase pemakaian kuota (0-100) untuk progress bar plan aktif.
     */
    public function getUsagePercentAttribute(): int
    {
        $quota = $this->quota_tokens;

        if ($quota <= 0) {
            return 0;
        }

        return (int) round(min(100, $this->tokens_used * 100 / $quota));
    }

    /**
     * Persentase kuota yang TERSISA (100 - pemakaian). Dipakai untuk progress
     * bar yang mengecil dari 100% (penuh) ke 0% seiring kuota terpakai.
     */
    public function getRemainingPercentAttribute(): int
    {
        return 100 - $this->usage_percent;
    }
}

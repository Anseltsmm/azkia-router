<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Promo Top Up (dikelola dari menu admin Event).
 *
 * Dua mode:
 *  - tier:    bonus tetap per jenjang nominal (mis. >=50rb -> bonus 5rb)
 *  - percent: bonus persentase dari nominal (mis. semua top-up +5%)
 */
class TopupPromoService
{
    public function enabled(): bool
    {
        return (bool) filter_var(AppSetting::get('topup_promo.enabled', '0'), FILTER_VALIDATE_BOOL);
    }

    public function type(): string
    {
        return AppSetting::get('topup_promo.type', 'tier') === 'percent' ? 'percent' : 'tier';
    }

    public function percent(): float
    {
        return (float) AppSetting::get('topup_promo.percent', '0');
    }

    /**
     * @return array<int, array{min_idr: int, bonus_idr: int}>
     */
    public function tiers(): array
    {
        $raw = AppSetting::get('topup_promo.tiers', '[]');
        $tiers = json_decode((string) $raw, true);
        if (! is_array($tiers)) {
            return [];
        }

        $tiers = array_values(array_filter($tiers, fn ($t) => isset($t['min_idr'], $t['bonus_idr'])));

        usort($tiers, fn ($a, $b) => (int) $a['min_idr'] <=> (int) $b['min_idr']);

        return $tiers;
    }

    /**
     * Hitung bonus (IDR) untuk nominal top-up tertentu. 0 jika promo nonaktif
     * atau nominal tidak memenuhi jenjang.
     */
    public function calculateBonusIdr(int $amountIdr): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        if ($this->type() === 'percent') {
            return (int) round($amountIdr * $this->percent() / 100);
        }

        // Jenjang sudah diurutkan ascending oleh tiers(): ambil jenjang
        // tertinggi yang memenuhi nominal (bukan sekadar bonus terbesar).
        $bonus = 0;
        foreach ($this->tiers() as $tier) {
            if ($amountIdr >= (int) $tier['min_idr']) {
                $bonus = (int) $tier['bonus_idr'];
            }
        }

        return $bonus;
    }

    /**
     * Data ringkas untuk halaman top-up (banner + kalkulator live).
     * Null jika promo nonaktif.
     */
    public function banner(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        if ($this->type() === 'percent') {
            return ['type' => 'percent', 'percent' => $this->percent(), 'tiers' => []];
        }

        $tiers = $this->tiers();
        if (! $tiers) {
            return null;
        }

        return ['type' => 'tier', 'percent' => 0, 'tiers' => $tiers];
    }
}

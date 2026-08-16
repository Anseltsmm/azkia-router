<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Kurs USD -> IDR realtime.
 *
 * Sumber: open.er-api.com (gratis, tanpa API key, update harian).
 * Hasil di-cache agar tidak membanjiri API; jika API gagal, nilai
 * cache lama (stale) dipakai, terakhir fallback dari config.
 */
class ExchangeRateService
{
    public const CACHE_KEY = 'exchange_rate_usd_idr';

    public const LAST_KNOWN_CACHE_KEY = 'exchange_rate_usd_idr_last_known';

    public function usdToIdr(): string
    {
        $cached = $this->validRate(Cache::get(self::CACHE_KEY));
        if ($cached !== null) {
            return $cached;
        }

        return $this->refresh();
    }

    /**
     * Paksa ambil kurs terbaru dari API lalu simpan ke cache.
     */
    public function refresh(): string
    {
        $rate = $this->fetchFresh();

        if ($rate !== null) {
            Cache::put(self::CACHE_KEY, $rate, now()->addSeconds((int) config('exchange.cache_ttl_seconds')));
            Cache::forever(self::LAST_KNOWN_CACHE_KEY, $rate);

            return $rate;
        }

        return $this->validRate(Cache::get(self::LAST_KNOWN_CACHE_KEY))
            ?? $this->validRate(config('exchange.fallback_rate'))
            ?? '16000';
    }

    /**
     * Konversi nilai USD menjadi IDR dengan kurs realtime.
     */
    public function usdToIdrAmount(int|float|string|null $usd): string
    {
        $amount = $usd ?? '0';

        if (! is_numeric($amount)) {
            $amount = '0';
        }

        $amount = (string) $amount;

        if (str_contains(strtolower($amount), 'e')) {
            $amount = rtrim(rtrim(sprintf('%.14F', (float) $amount), '0'), '.');
        }

        return bcmul($amount, $this->usdToIdr(), 6);
    }

    private function fetchFresh(): ?string
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get(config('exchange.rate_api_url'));
            if ($response->successful()) {
                return $this->validRate($response->json('rates.IDR'));
            }
        } catch (\Throwable $e) {
            logger()->warning('Gagal mengambil kurs USD/IDR dari API: '.$e->getMessage());
        }

        return null;
    }

    private function validRate(mixed $rate): ?string
    {
        if (! is_int($rate) && ! is_float($rate) && ! is_string($rate)) {
            return null;
        }

        $rate = (string) $rate;

        return is_numeric($rate) && bccomp($rate, '0', 8) === 1 ? $rate : null;
    }
}

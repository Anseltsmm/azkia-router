<?php

namespace Tests\Feature;

use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    public function test_fetches_realtime_rate_from_api_and_caches_it(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['IDR' => 16250.5]], 200),
        ]);

        $rate = app(ExchangeRateService::class)->usdToIdr();

        $this->assertSame('16250.5', $rate);
        $this->assertSame('16250.5', Cache::get(ExchangeRateService::CACHE_KEY));
        $this->assertSame('16250.5', Cache::get(ExchangeRateService::LAST_KNOWN_CACHE_KEY));
    }

    public function test_uses_cached_rate_without_hitting_api(): void
    {
        Cache::put(ExchangeRateService::CACHE_KEY, 15999.0);
        Http::fake();

        $rate = app(ExchangeRateService::class)->usdToIdr();

        $this->assertSame('15999', $rate);
        Http::assertNothingSent();
    }

    public function test_uses_fallback_rate_when_api_fails_and_no_cache(): void
    {
        Cache::forget(ExchangeRateService::CACHE_KEY);
        Cache::forget(ExchangeRateService::LAST_KNOWN_CACHE_KEY);
        Http::fake([
            'open.er-api.com/*' => Http::response([], 500),
        ]);

        $rate = app(ExchangeRateService::class)->usdToIdr();

        $this->assertSame((string) config('exchange.fallback_rate'), $rate);
    }

    public function test_keeps_last_known_rate_after_fresh_cache_expires(): void
    {
        Cache::put(ExchangeRateService::LAST_KNOWN_CACHE_KEY, '16300.125');
        Cache::forget(ExchangeRateService::CACHE_KEY);
        Http::fake([
            'open.er-api.com/*' => Http::response([], 503),
        ]);

        $rate = app(ExchangeRateService::class)->usdToIdr();

        $this->assertSame('16300.125', $rate);
        $this->assertNull(Cache::get(ExchangeRateService::CACHE_KEY));
    }

    public function test_converts_usd_amount_to_idr(): void
    {
        Cache::put(ExchangeRateService::CACHE_KEY, 16000.0);
        Http::fake();

        $this->assertSame('160000.000000', app(ExchangeRateService::class)->usdToIdrAmount('10'));
    }
}

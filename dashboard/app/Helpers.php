<?php

use App\Services\ExchangeRateService;

if (! function_exists('capability_options')) {
    /**
     * Daftar kemampuan model yang dikenal (urut sesuai tampilan form).
     * Dipakai di form Add/Edit Model admin; ikon lihat capability_icon().
     */
    function capability_options(): array
    {
        return [
            'chat' => 'chat',
            'completion' => 'completion',
            'embedding' => 'embedding',
            'tool' => 'tool (function calling)',
            'vision' => 'vision (image understanding)',
            'image' => 'image (image generation)',
            'audio' => 'audio (audio understanding)',
            'tts' => 'tts (speech output)',
            'reasoning' => 'reasoning (deep thinking)',
        ];
    }
}

if (! function_exists('capability_icon')) {
    /**
     * Ikon SVG inline untuk kemampuan model (dipakai sebagai pengganti teks).
     * Mengembalikan string SVG kecil yang aman untuk {!! !!}.
     */
    function capability_icon(string $cap): string
    {
        $icons = [
            'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
            'completion' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h10"/><path d="M4 18h7"/></svg>',
            'embedding' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
            'tool' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
            'vision' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
            'image' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
            'audio' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
            'tts' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>',
            'reasoning' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44A2.5 2.5 0 0 1 4 17.5v-4a2.5 2.5 0 0 1-2-2.45v-2A2.5 2.5 0 0 1 4.5 6.6V5A2.5 2.5 0 0 1 7 2.5h2.5z"/><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44A2.5 2.5 0 0 0 20 17.5v-4a2.5 2.5 0 0 0 2-2.45v-2A2.5 2.5 0 0 0 19.5 6.6V5A2.5 2.5 0 0 0 17 2.5h-2.5z"/></svg>',
        ];

        return $icons[strtolower($cap)] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3"/><path d="M12 18v3"/><path d="M3 12h3"/><path d="M18 12h3"/><path d="M5.6 5.6l2.1 2.1"/><path d="M16.3 16.3l2.1 2.1"/><path d="M18.4 5.6l-2.1 2.1"/><path d="M7.7 16.3l-2.1 2.1"/></svg>';
    }
}

if (! function_exists('format_compact_number')) {
    /**
     * Format a number into a compact human-readable form.
     *
     * 1000 -> 1K, 8192 -> 8.2K, 128000 -> 128K, 1000000 -> 1M, 1280000 -> 1.28M
     */
    function format_compact_number(int|float $value): string
    {
        $value = (float) $value;

        if ($value >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 2, '.', ''), '0'), '.').'M';
        }

        if ($value >= 1_000) {
            $k = $value / 1_000;

            if ($k >= 999.95) { // dibulatkan jadi "1000K" -> tampilkan dalam satuan M
                return rtrim(rtrim(number_format($value / 1_000_000, 2, '.', ''), '0'), '.').'M';
            }

            return rtrim(rtrim(number_format($k, 1, '.', ''), '0'), '.').'K';
        }

        return (string) (int) $value;
    }
}

if (! function_exists('usd_to_idr_rate')) {
    /**
     * Kurs realtime 1 USD berapa Rupiah (open.er-api.com, di-cache).
     */
    function usd_to_idr_rate(): string
    {
        return app(ExchangeRateService::class)->usdToIdr();
    }
}

if (! function_exists('usd_to_idr')) {
    /**
     * Konversi nilai USD ke IDR dengan kurs realtime.
     */
    function usd_to_idr(int|float|string|null $usd): string
    {
        return app(ExchangeRateService::class)->usdToIdrAmount($usd);
    }
}

if (! function_exists('format_idr_from_usd')) {
    /**
     * Format nilai yang disimpan dalam USD menjadi Rupiah (IDR)
     * menggunakan kurs realtime. Dipakai di seluruh dashboard karena
     * harga & saldo disimpan dalam USD.
     */
    function format_idr_from_usd(int|float|string|null $usd): string
    {
        return format_idr(usd_to_idr($usd));
    }
}

if (! function_exists('format_idr_usd')) {
    /**
     * Format nilai USD sebagai pasangan Rupiah + USD, mis.
     * "Rp 3.217 · $0.18" — menampilkan kedua mata uang sekaligus.
     */
    function format_idr_usd(int|float|string|null $usd): string
    {
        return format_idr_from_usd($usd).' · '.format_usd($usd);
    }
}

if (! function_exists('format_usd')) {
    /**
     * Format nilai dalam USD: 1234.5 -> "$1,234.50".
     */
    function format_usd(int|float|string|null $value): string
    {
        return '$'.number_format((float) ($value ?? 0), 2, '.', ',');
    }
}

if (! function_exists('format_idr')) {
    /**
     * Format nilai mata uang Rupiah (IDR) dengan gaya Indonesia.
     *
     * Nilai besar tanpa desimal: 1500000 -> "Rp 1.500.000"
     * Nilai sedang dengan 2 desimal: 1234.5 -> "Rp 1.234,50"
     * Nilai kecil dengan 4 desimal: 0.2633 -> "Rp 0,2633"
     */
    function format_idr(int|float|string|null $value): string
    {
        $value = (float) ($value ?? 0);
        $abs = abs($value);

        if ($value == 0) {
            return 'Rp 0';
        }

        if ($abs >= 10000) {
            return 'Rp '.number_format($value, 0, ',', '.');
        }

        if ($abs >= 1) {
            return 'Rp '.number_format($value, 2, ',', '.');
        }

        return 'Rp '.number_format($value, 4, ',', '.');
    }
}

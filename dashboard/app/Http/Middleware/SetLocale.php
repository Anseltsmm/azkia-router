<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Berlaku untuk semua pengunjung (guest maupun login) di domain utama,
        // agar landing page & halaman publik ikut ganti bahasa. Admin domain
        // (admin.azkia.cloud) selalu tetap di bahasa default.
        if ($request->getHost() !== 'admin.azkia.cloud') {
            // Prioritas: session (switch dalam sesi berjalan) → cookie persisten
            // (kunjungan berikutnya) → bahasa default aplikasi.
            $locale = $request->session()->get('locale', $request->cookie('locale', config('app.locale')));

            if (in_array($locale, config('app.supported_locales'), true)) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}

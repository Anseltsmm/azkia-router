<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackReferral
{
    /**
     * Tangkap kode referral dari query string (?ref=CODE) untuk pengunjung
     * yang belum login, lalu simpan ke session. Saat pengunjung mendaftar
     * (via Google), AuthController memakai session ini untuk mengaitkan
     * akun barunya ke referrer.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guest() && $request->query->has('ref')) {
            $code = strtoupper(trim((string) $request->query('ref')));

            if ($code !== '' && User::where('referral_code', $code)->exists()) {
                $request->session()->put('referral_code', $code);
            }
        }

        return $next($request);
    }
}

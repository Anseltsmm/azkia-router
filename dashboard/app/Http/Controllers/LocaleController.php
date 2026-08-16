<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales'))],
        ]);

        $request->session()->put('locale', $data['locale']);

        // Persisten lintas kunjungan (cookie 1 tahun) — pilihan bahasa tetap
        // tersimpan walau session baru / browser ditutup.
        return back()->withCookie(cookie()->forever('locale', $data['locale']));
    }
}

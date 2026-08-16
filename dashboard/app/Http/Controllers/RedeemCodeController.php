<?php

namespace App\Http\Controllers;

use App\Services\RedeemCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RedeemCodeController extends Controller
{
    public function create()
    {
        return view('user.redeem-code', ['idempotency' => (string) Str::uuid()]);
    }

    public function store(Request $request, RedeemCodeService $service)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64'], 'idempotency' => ['required', 'uuid']]);
        $redemption = $service->redeem($request->user(), $data['code'], $data['idempotency'], $request->ip());

        return redirect()->route('redeem-codes.create')->with('success', 'Kode berhasil digunakan. Saldo USD '.number_format((float) $redemption->amount, 6, '.', '').' telah ditambahkan.');
    }
}

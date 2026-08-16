<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedeemCode;
use App\Models\RedeemCodeBatch;
use App\Services\RedeemCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RedeemCodeController extends Controller
{
    public function index()
    {
        return view('admin.redeem-codes', [
            'batches' => RedeemCodeBatch::withCount('codes')->withSum('codes', 'uses_count')->with(['codes' => fn ($query) => $query->latest()->limit(100)])->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, RedeemCodeService $service)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'], 'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,6})?$/', 'decimal:0,6', 'gt:0'],
            'expires_at' => ['nullable', 'date', 'after:now'], 'max_total_uses' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_uses_per_account' => ['required', 'integer', 'min:1', 'lte:max_total_uses'], 'max_uses_per_ip' => ['required', 'integer', 'min:1', 'lte:max_total_uses'],
            'eligible_users' => ['required', 'in:all,topup'],
            'current_password' => ['required', 'string'], 'generation_idempotency' => ['required', 'uuid'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini tidak valid.']);
        }
        $result = $service->generate($request->user(), $data);
        if ($result['codes'] === null) {
            return redirect()->route('admin.redeem-codes.index')->with('success', 'Batch ini sudah pernah dibuat. Plaintext kode tidak dapat ditampilkan kembali.');
        }

        return response()->view('admin.redeem-codes-result', $result)->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
    }

    public function disableBatch(RedeemCodeBatch $batch)
    {
        $batch->update(['is_active' => false]);

        return back()->with('success', 'Batch dinonaktifkan.');
    }

    public function disableCode(RedeemCode $code)
    {
        $code->update(['is_active' => false]);

        return back()->with('success', 'Kode dinonaktifkan.');
    }
}

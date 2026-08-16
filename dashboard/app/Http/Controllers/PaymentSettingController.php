<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class PaymentSettingController extends Controller
{
    public function edit()
    {
        return view('admin.payment-settings', [
            'setting' => PaymentSetting::firstOrNew(['provider' => 'tripay']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:sandbox,production'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'private_key' => ['nullable', 'string', 'max:1000'],
            'merchant_code' => ['nullable', 'string', 'max:255'],
            'minimum_topup' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'expiry_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $setting = PaymentSetting::firstOrNew(['provider' => 'tripay']);
        $setting->mode = $data['mode'];
        $setting->minimum_topup = $data['minimum_topup'];
        $setting->expiry_hours = $data['expiry_hours'];
        $setting->is_active = $request->boolean('is_active');

        foreach (['api_key', 'private_key', 'merchant_code'] as $key) {
            if (filled($data[$key] ?? null)) {
                $setting->{$key.'_encrypted'} = Crypt::encryptString($data[$key]);
            }
        }

        if ($setting->is_active && (! $setting->api_key_encrypted || ! $setting->private_key_encrypted || ! $setting->merchant_code_encrypted)) {
            return back()->withInput()->withErrors(['credentials' => 'Lengkapi seluruh kredensial sebelum mengaktifkan Tripay.']);
        }

        $setting->save();

        return back()->with('success', 'Pengaturan Tripay berhasil disimpan.');
    }
}

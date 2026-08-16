@extends('layouts.app')

@section('content')
<div class="top"><div><h2>Payment Gateway</h2><p>Konfigurasi Tripay untuk top up saldo otomatis.</p></div><span class="badge {{ $setting->is_active ? 'green' : 'amber' }}">{{ $setting->is_active ? 'aktif' : 'nonaktif' }}</span></div>
@include('admin._alerts')
<div class="card" style="max-width:760px">
    <form method="post" action="{{ route('admin.payment-settings.update') }}">@csrf @method('patch')
        <div class="form-row">
            <div><label class="muted">Mode</label><select name="mode"><option value="sandbox" @selected(old('mode', $setting->mode ?: 'sandbox') === 'sandbox')>Sandbox</option><option value="production" @selected(old('mode', $setting->mode) === 'production')>Production</option></select></div>
            <div><label class="muted">Merchant Code</label><input name="merchant_code" type="password" autocomplete="new-password" placeholder="{{ $setting->merchant_code_encrypted ? 'Tersimpan — kosongkan jika tidak diubah' : 'Kode merchant Tripay' }}"></div>
        </div>
        <label class="muted">API Key</label>
        <input name="api_key" type="password" autocomplete="new-password" placeholder="{{ $setting->api_key_encrypted ? 'Tersimpan — kosongkan jika tidak diubah' : 'API key Tripay' }}">
        <label class="muted">Private Key</label>
        <input name="private_key" type="password" autocomplete="new-password" placeholder="{{ $setting->private_key_encrypted ? 'Tersimpan — kosongkan jika tidak diubah' : 'Private key Tripay' }}">
        <div class="form-row">
            <div><label class="muted">Minimum Top Up (IDR)</label><input name="minimum_topup" type="number" min="1000" max="10000000" step="1000" value="{{ old('minimum_topup', $setting->minimum_topup ?: 10000) }}" required></div>
            <div><label class="muted">Masa Berlaku Pembayaran (Jam)</label><input name="expiry_hours" type="number" min="1" max="72" value="{{ old('expiry_hours', $setting->expiry_hours ?: 24) }}" required></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;color:var(--body);margin:4px 0 16px"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $setting->is_active)) style="width:16px;height:16px;margin:0"> Aktifkan pembayaran Tripay</label>
        <button>Simpan Pengaturan</button>
    </form>
</div>
<div class="section card" style="max-width:760px">
    <h3>Callback URL</h3>
    <div class="key">{{ route('tripay.callback') }}</div>
    <p class="muted" style="margin-bottom:0">Masukkan URL ini pada konfigurasi callback merchant Tripay. Kredensial disimpan terenkripsi menggunakan APP_KEY Laravel.</p>
</div>
@endsection

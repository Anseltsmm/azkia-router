@extends('layouts.app')

@section('content')
<div class="top"><div><h2>Event</h2><p>Kelola program promo &amp; event — saat ini: Program Referral.</p></div><span class="badge {{ $enabled ? 'green' : 'amber' }}">{{ $enabled ? 'aktif' : 'nonaktif' }}</span></div>
@include('admin._alerts')
<div class="card" style="max-width:760px">
    <form method="post" action="{{ route('admin.event.update') }}">@csrf @method('patch')
        <div class="form-row">
            <div><label class="muted">Reward Referrer (USD)</label><input name="reward_usd" type="number" min="0" max="1000" step="0.01" value="{{ old('reward_usd', $rewardUsd) }}" required></div>
            <div><label class="muted">Min. Top-up Pertama Teman (IDR)</label><input name="min_topup_idr" type="number" min="1000" max="10000000" step="1000" value="{{ old('min_topup_idr', $minTopupIdr) }}" required></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;color:var(--body);margin:4px 0 16px"><input name="enabled" type="checkbox" value="1" @checked(old('enabled', $enabled)) style="width:16px;height:16px;margin:0"> Aktifkan program referral</label>
        <button>Simpan Pengaturan</button>
    </form>
</div>
<div class="section grid" style="max-width:760px">
    <div class="card"><h3>Total Teman Direferensikan</h3><div class="metric small">{{ $totalReferrals }}</div></div>
    <div class="card"><h3>Reward Dibayarkan</h3><div class="metric small">{{ $rewardsPaid }} · ${{ number_format((float) $rewardsTotalUsd, 2) }}</div></div>
    <div class="card"><h3>Menunggu Top-up</h3><div class="metric small">{{ $pendingReferrals }}</div></div>
</div>
<div class="section card" style="max-width:760px">
    <h3>Cara Kerja Referral</h3>
    <ol style="margin:0;padding-left:18px;color:var(--body);font-size:13.5px;line-height:1.8">
        <li>User membagikan link <code>https://azkia.cloud/?ref=KODE</code> (ada di halaman <b>Referral</b> user).</li>
        <li>Teman membuka link &amp; mendaftar/login Google — akun otomatis terhubung ke referrer.</li>
        <li>Saat teman melakukan top-up pertama minimal nominal di atas dan lunas, referrer otomatis mendapat reward saldo.</li>
        <li>Reward hanya diberikan sekali per teman.</li>
    </ol>
</div>
@endsection

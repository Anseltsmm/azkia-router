@extends('layouts.app')

@section('content')
<style>
    .tier-row{display:grid;grid-template-columns:1fr 1fr 40px;gap:8px;align-items:center;margin-bottom:8px}
    .tier-row input{margin:0}
    .tier-row button{width:40px;height:38px;padding:0;background:var(--red-soft);color:var(--red-ink);border-color:var(--red-line);box-shadow:none}
    .tier-row button:hover{background:#fde3e3;box-shadow:none}
</style>
<div class="top"><div><h2>Event</h2><p>Kelola program promo &amp; event untuk pengguna.</p></div></div>
@include('admin._alerts')

{{-- ============ Event Top Up ============ --}}
<div class="card" style="max-width:760px">
    <h3>Event Top Up <span class="badge {{ $topupPromo->enabled() ? 'green' : 'amber' }}">{{ $topupPromo->enabled() ? 'aktif' : 'nonaktif' }}</span></h3>
    <p class="muted" style="margin:0 0 14px">Bonus saldo otomatis untuk top-up pengguna. Bonus dikonversi ke USD memakai kurs saat transaksi dibuat.</p>
    <form method="post" action="{{ route('admin.event.update-topup') }}">@csrf @method('patch')
        <div class="form-row">
            <div><label class="muted">Jenis Bonus</label>
                <select name="type">
                    <option value="tier" @selected(old('type', $topupPromo->type()) === 'tier')>Jenjang nominal (tier)</option>
                    <option value="percent" @selected(old('type', $topupPromo->type()) === 'percent')>Persentase</option>
                </select>
            </div>
            <div id="percent-wrap" style="{{ $topupPromo->type() === 'percent' ? '' : 'display:none' }}"><label class="muted">Bonus (%)</label><input name="percent" type="number" min="0" max="100" step="0.5" value="{{ old('percent', $topupPromo->percent()) }}"></div>
        </div>
        <div id="tiers-wrap" style="{{ $topupPromo->type() === 'tier' ? '' : 'display:none' }}">
            <label class="muted">Jenjang Bonus (top-up ≥ min → bonus)</label>
            <div id="tiers">
                @forelse($topupPromo->tiers() as $tier)
                <div class="tier-row">
                    <input name="tiers[{{ $loop->index }}][min_idr]" type="number" min="1000" step="1000" value="{{ $tier['min_idr'] }}" placeholder="Min (IDR)">
                    <input name="tiers[{{ $loop->index }}][bonus_idr]" type="number" min="0" step="1000" value="{{ $tier['bonus_idr'] }}" placeholder="Bonus (IDR)">
                    <button type="button" onclick="this.closest('.tier-row').remove()">✕</button>
                </div>
                @empty
                <div class="tier-row">
                    <input name="tiers[0][min_idr]" type="number" min="1000" step="1000" placeholder="Min (IDR)">
                    <input name="tiers[0][bonus_idr]" type="number" min="0" step="1000" placeholder="Bonus (IDR)">
                    <button type="button" onclick="this.closest('.tier-row').remove()">✕</button>
                </div>
                @endforelse
            </div>
            <button type="button" class="secondary" id="add-tier" style="margin:4px 0 12px">+ Tambah Jenjang</button>
        </div>
        <label style="display:flex;align-items:center;gap:8px;color:var(--body);margin:4px 0 16px"><input name="enabled" type="checkbox" value="1" @checked(old('enabled', $topupPromo->enabled())) style="width:16px;height:16px;margin:0"> Aktifkan event top up</label>
        <button>Simpan Event Top Up</button>
    </form>
</div>

{{-- ============ Program Referral ============ --}}
<div class="section card" style="max-width:760px">
    <h3>Program Referral <span class="badge {{ $enabled ? 'green' : 'amber' }}">{{ $enabled ? 'aktif' : 'nonaktif' }}</span></h3>
    <form method="post" action="{{ route('admin.event.update') }}">@csrf @method('patch')
        <div class="form-row">
            <div><label class="muted">Reward Referrer (USD)</label><input name="reward_usd" type="number" min="0" max="1000" step="0.01" value="{{ old('reward_usd', $rewardUsd) }}" required></div>
            <div><label class="muted">Min. Top-up Pertama Teman (IDR)</label><input name="min_topup_idr" type="number" min="1000" max="10000000" step="1000" value="{{ old('min_topup_idr', $minTopupIdr) }}" required></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;color:var(--body);margin:4px 0 16px"><input name="enabled" type="checkbox" value="1" @checked(old('enabled', $enabled)) style="width:16px;height:16px;margin:0"> Aktifkan program referral</label>
        <button>Simpan Referral</button>
    </form>
    <div class="grid" style="margin-top:18px">
        <div><h3 style="font-size:12.5px">Total Teman Direferensikan</h3><div class="metric small">{{ $totalReferrals }}</div></div>
        <div><h3 style="font-size:12.5px">Reward Dibayarkan</h3><div class="metric small">{{ $rewardsPaid }} · ${{ number_format((float) $rewardsTotalUsd, 2) }}</div></div>
        <div><h3 style="font-size:12.5px">Menunggu Top-up</h3><div class="metric small">{{ $pendingReferrals }}</div></div>
    </div>
</div>

<script>
    (function () {
        var type = document.querySelector('select[name="type"]');
        var tiersWrap = document.getElementById('tiers-wrap');
        var percentWrap = document.getElementById('percent-wrap');
        var addTier = document.getElementById('add-tier');
        if (type) {
            type.addEventListener('change', function () {
                var isPercent = type.value === 'percent';
                tiersWrap.style.display = isPercent ? 'none' : '';
                percentWrap.style.display = isPercent ? '' : 'none';
            });
        }
        if (addTier) {
            addTier.addEventListener('click', function () {
                var idx = document.querySelectorAll('.tier-row').length;
                var row = document.createElement('div');
                row.className = 'tier-row';
                row.innerHTML = '<input name="tiers[' + idx + '][min_idr]" type="number" min="1000" step="1000" placeholder="Min (IDR)">' +
                    '<input name="tiers[' + idx + '][bonus_idr]" type="number" min="0" step="1000" placeholder="Bonus (IDR)">' +
                    '<button type="button" onclick="this.closest(\'.tier-row\').remove()">✕</button>';
                document.getElementById('tiers').appendChild(row);
            });
        }
    })();
</script>
@endsection

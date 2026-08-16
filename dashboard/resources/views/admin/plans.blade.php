@extends('layouts.app')

@section('content')
<style>
    .plan-models{display:grid;grid-template-columns:1fr 1fr;gap:4px;max-height:170px;overflow-y:auto;border:1px solid var(--line);border-radius:9px;padding:8px;margin:4px 0 10px;background:var(--input);scrollbar-width:thin}
    .plan-model{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--body);cursor:pointer;padding:3px 4px;border-radius:6px}
    .plan-model:hover{background:var(--soft)}
    .plan-model input{width:14px;height:14px;margin:0;flex:0 0 auto}
    /* Chip model di kolom tabel plan */
    .plan-chips{display:flex;flex-wrap:wrap;gap:4px;align-items:center;max-width:300px}
    .plan-chip{display:inline-flex;align-items:center;gap:5px;max-width:170px;background:var(--soft);border:1px solid var(--line);border-radius:999px;padding:2px 8px;font-size:11.5px;font-weight:600;color:var(--body);white-space:nowrap;overflow:hidden}
    .plan-chip img{width:14px;height:14px;border-radius:4px;object-fit:contain;flex:0 0 auto}
    .plan-chip .pc-name{overflow:hidden;text-overflow:ellipsis}
    .plan-chip.more{cursor:pointer;background:var(--brand-soft);border-color:var(--brand-line);color:var(--brand);font-weight:700}
    .plan-chip.more:hover{background:var(--brand-line);color:var(--brand)}
    .plan-chip.all{background:var(--green-soft);border-color:var(--green-line);color:var(--green-ink)}
    .plan-chips-hidden{display:flex;flex-wrap:wrap;gap:4px;align-items:center}
    .plan-chips-hidden[hidden]{display:none}
    .edit-details{display:inline-block;vertical-align:middle;margin-right:6px}
    .edit-details summary{display:inline-block;padding:5px 10px;border:1px solid var(--line);border-radius:8px;font-size:12px;cursor:pointer}
    .edit-popup{position:absolute;z-index:10;width:340px;max-width:calc(100vw - 28px);margin-top:6px;background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:14px;box-shadow:0 12px 32px rgba(0,0,0,.18)}
    /* Mobile: form edit jadi bottom sheet penuh layar agar tidak terpotong/tergeser oleh tabel yang scroll. */
    @media(max-width:639.98px){
        .plan-models{grid-template-columns:1fr}
        .edit-popup{position:fixed;inset:auto 12px 12px;width:auto;max-width:none;margin-top:0;max-height:calc(100dvh - 24px);overflow-y:auto;z-index:120}
    }
</style>
<div class="top">
    <div><h2>Plans</h2><p>Paket kuota token (bundle) yang bisa dibeli user dengan saldo — selain PAYG.</p></div>
    <span class="pill">{{ $plans->total() }} plans</span>
</div>

@include('admin._alerts')

<div class="section grid2">
    <div class="card">
        <h3>Add Plan</h3>
        <form id="add-plan-form" method="post" action="{{ route('admin.plans.store') }}">@csrf
            <label class="muted">Name</label><input name="name" placeholder="Plan Harian 15M" required>
            <label class="muted">Slug (otomatis)</label><input name="slug" id="plan-slug" value="{{ old('slug') }}" placeholder="diisi otomatis dari nama" readonly>
            <p class="muted" style="font-size:11.5px;margin:2px 0 0">Di-generate otomatis dari nama — tidak perlu diisi manual.</p>
            <label class="muted">Total Tokens</label><input name="total_tokens" type="number" min="1" placeholder="15000000" required>
            <label class="muted">Daily Limit Tokens (opsional, reset waktu server)</label><input name="daily_limit_tokens" type="number" min="1" placeholder="15000000">
            <label class="muted">Rate Limit Per Menit (opsional — kosong = tanpa batas)</label><input name="rate_limit_per_minute" type="number" min="1" placeholder="60" value="{{ old('rate_limit_per_minute') }}">
            <label class="muted">Durasi</label>
            <select name="duration_hours">
                @foreach(\App\Models\Plan::durationOptions() as $hours => $label)
                <option value="{{ $hours }}" @selected((string) old('duration_hours', 24) === (string) $hours)>{{ $label }}</option>
                @endforeach
            </select>
            <label style="display:flex;align-items:center;gap:8px;margin:10px 0;font-size:12.5px;color:var(--muted);cursor:pointer"><input type="checkbox" name="resets_daily" value="1" @checked(old('resets_daily')) style="width:15px;height:15px;margin:0;flex:0 0 auto"> Gratis · reset harian (tanpa masa berlaku)</label>
            <div class="grid2">
                <div>
                    <label class="muted">Harga USD</label><input name="price_usd" type="number" step="0.01" min="0" placeholder="0.70" required>
                </div>
                <div>
                    <label class="muted">Harga IDR (pair, otomatis)</label>
                    <div style="position:relative">
                        <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12.5px;font-weight:600;pointer-events:none">Rp</span>
                        <input name="price_idr" type="number" step="1" min="0" placeholder="11200" style="padding-left:34px">
                    </div>
                </div>
            </div>
            <label class="muted">Stok (opsional — kosong = tanpa batas)</label><input name="stock" type="number" min="0" placeholder="50">
            <label class="muted">Model yang boleh dipakai (kosong = semua model)</label>
            <div class="plan-models">
                @forelse($models as $model)
                <label class="plan-model"><input type="checkbox" name="model_ids[]" value="{{ $model->id }}" @checked(in_array($model->id, old('model_ids', []), true))> {{ $model->public_name }}</label>
                @empty
                <span class="muted" style="font-size:12px;grid-column:1/-1">Belum ada model aktif.</span>
                @endforelse
            </div>
            <button>Save Plan</button>
        </form>
    </div>
    <div class="card">
        <h3>Catatan</h3>
        <p class="muted" style="font-size:13.5px;margin:0 0 10px">Kuota plan berlaku <strong>per user</strong> — semua API key user berbagi kuota yang sama. Token dipakai <strong>sebelum</strong> saldo PAYG; bila PAYG nonaktif dan kuota plan habis, request ditolak.</p>
        <p class="muted" style="font-size:13.5px;margin:0">Batas harian (bila diisi) di-reset mengikuti tanggal server (UTC). Contoh: plan harian = total 15M tanpa batas harian, durasi 24 jam; plan mingguan = total 100M dengan batas harian 15M, durasi 168 jam.</p>
    </div>
</div>

<div class="section card">
    <h3>Daftar Plan</h3>
    <div class="table-wrap"><table>
        <tr><th>Plan</th><th>Kuota</th><th>Batas Harian</th><th>Rate/Menit</th><th>Durasi</th><th>Harga</th><th>Stok</th><th>Model</th><th>Pembeli</th><th>Status</th><th>Action</th></tr>
        @forelse($plans as $plan)
        <tr>
            <td>
                <strong>{{ $plan->name }}</strong><br>
                <span class="muted" style="font-size:12px">{{ $plan->slug }}</span>
            </td>
            <td>{{ $plan->tokens_label }}</td>
            <td>{{ $plan->daily_limit_label ?? '—' }}</td>
            <td>{{ $plan->rate_limit_per_minute ? $plan->rate_limit_per_minute.'/menit' : '—' }}</td>
            <td>{{ $plan->duration_label }}</td>
            <td>
                {{ format_usd($plan->price_usd) }}
                @if($plan->price_idr)<br><span class="muted" style="font-size:12px">Rp {{ number_format($plan->price_idr, 0, ',', '.') }}</span>@endif
            </td>
            <td>{{ $plan->stock_label }}</td>
            <td>
                <div class="plan-chips">
                    @forelse($plan->models->take(3) as $model)
                    <span class="plan-chip" title="{{ $model->public_name }}">@if($model->icon_url)<img src="{{ $model->icon_url }}" alt="">@endif<span class="pc-name">{{ $model->public_name }}</span></span>
                    @empty
                    <span class="plan-chip all">Semua model</span>
                    @endforelse
                    @if($plan->models->count() > 3)
                    <span class="plan-chip more" role="button" tabindex="0" data-more-chips title="Klik untuk menampilkan semua ({{ $plan->models->count() }} model)">+{{ $plan->models->count() - 3 }}</span>
                    <span class="plan-chips-hidden" hidden>
                        @foreach($plan->models->skip(3) as $model)
                        <span class="plan-chip" title="{{ $model->public_name }}">@if($model->icon_url)<img src="{{ $model->icon_url }}" alt="">@endif<span class="pc-name">{{ $model->public_name }}</span></span>
                        @endforeach
                    </span>
                    @endif
                </div>
            </td>
            <td>{{ $plan->user_plans_count }}</td>
            <td><span class="badge {{ $plan->is_active ? 'green' : 'red' }}">{{ $plan->is_active ? 'active' : 'off' }}</span></td>
            <td style="white-space:nowrap">
                <details class="edit-details">
                    <summary class="secondary">Edit</summary>
                    <div class="edit-popup">
                        <form method="post" action="{{ route('admin.plans.update', $plan) }}">@csrf @method('patch')
                            <label class="muted">Name</label><input name="name" value="{{ $plan->name }}" required>
                            <label class="muted">Total Tokens</label><input name="total_tokens" type="number" min="1" value="{{ $plan->total_tokens }}" required>
                            <label class="muted">Daily Limit Tokens</label><input name="daily_limit_tokens" type="number" min="1" value="{{ $plan->daily_limit_tokens }}">
                            <label class="muted">Rate Limit Per Menit (kosong = tanpa batas)</label><input name="rate_limit_per_minute" type="number" min="1" value="{{ $plan->rate_limit_per_minute }}">
                            <label class="muted">Durasi</label>
                            <select name="duration_hours">
                                @foreach(\App\Models\Plan::durationOptions() as $hours => $label)
                                <option value="{{ $hours }}" @selected((string) $plan->duration_hours === (string) $hours)>{{ $label }}</option>
                                @endforeach
                                @if($plan->duration_hours !== null && ! in_array($plan->duration_hours, array_keys(\App\Models\Plan::durationOptions()), true))
                                <option value="{{ $plan->duration_hours }}" selected>{{ $plan->duration_hours }} jam (kustom)</option>
                                @endif
                            </select>
                            <label style="display:flex;align-items:center;gap:8px;margin:10px 0;font-size:12.5px;color:var(--muted);cursor:pointer"><input type="checkbox" name="resets_daily" value="1" @checked($plan->resets_daily) style="width:15px;height:15px;margin:0;flex:0 0 auto"> Gratis · reset harian (tanpa masa berlaku)</label>
                            <div class="grid2">
                                <div><label class="muted">Harga USD</label><input name="price_usd" type="number" step="0.01" min="0" value="{{ $plan->price_usd }}" required></div>
                                <div>
                                    <label class="muted">Harga IDR (otomatis)</label>
                                    <div style="position:relative">
                                        <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12.5px;font-weight:600;pointer-events:none">Rp</span>
                                        <input name="price_idr" type="number" step="1" min="0" value="{{ $plan->price_idr }}" style="padding-left:34px">
                                    </div>
                                </div>
                            </div>
                            <label class="muted">Stok (kosong = tanpa batas)</label><input name="stock" type="number" min="0" value="{{ $plan->stock }}">
                            <label class="muted">Model yang boleh dipakai (kosong = semua model)</label>
                            <div class="plan-models">
                                @forelse($models as $model)
                                <label class="plan-model"><input type="checkbox" name="model_ids[]" value="{{ $model->id }}" @checked(in_array($model->id, old('model_ids', $plan->models->pluck('id')->all()), true))> {{ $model->public_name }}</label>
                                @empty
                                <span class="muted" style="font-size:12px;grid-column:1/-1">Belum ada model aktif.</span>
                                @endforelse
                            </div>
                            <label style="display:flex;align-items:center;gap:8px;margin:10px 0"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Aktif</label>
                            <button>Update</button>
                        </form>
                    </div>
                </details>
                <form method="post" action="{{ route('admin.plans.toggle', $plan) }}" style="display:inline">@csrf @method('patch')<button class="secondary">{{ $plan->is_active ? 'Disable' : 'Enable' }}</button></form>
                <form method="post" action="{{ route('admin.plans.destroy', $plan) }}" style="display:inline" onsubmit="return confirm('Hapus plan ini? Pembelian lama tetap tersimpan.')">@csrf @method('delete')<button class="secondary" style="color:var(--red-ink)">Delete</button></form>
            </td>
        </tr>
        @empty
        <tr><td colspan="11" style="text-align:center;color:var(--muted)">Belum ada plan — tambahkan di atas.</td></tr>
        @endforelse
    </table></div>
    <div style="margin-top:14px">{{ $plans->links() }}</div>
</div>

<script>
(function () {
    // Pair IDR terisi otomatis realtime saat admin mengetik harga USD
    // (kurs realtime dari server). Bisa tetap diedit manual.
    var rate = {{ $usdRate }};
    document.querySelectorAll('form').forEach(function (form) {
        var usd = form.querySelector('input[name="price_usd"]');
        var idr = form.querySelector('input[name="price_idr"]');
        if (!usd || !idr) return;
        function update() {
            var v = parseFloat(usd.value);
            idr.value = (isNaN(v) || v <= 0) ? '' : String(Math.round(v * rate));
        }
        usd.addEventListener('input', update);
        if (!idr.value) update(); // isi otomatis saat edit bila IDR belum tersimpan
    });

    // Slug otomatis: terisi live dari kolom nama (hanya form Add Plan).
    var addForm = document.getElementById('add-plan-form');
    if (addForm) {
        var nameInput = addForm.querySelector('input[name="name"]');
        var slugInput = addForm.querySelector('input[name="slug"]');
        if (nameInput && slugInput) {
            function slugify(value) {
                return String(value).toLowerCase().trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .replace(/-{2,}/g, '-');
            }
            function syncSlug() {
                slugInput.value = slugify(nameInput.value);
            }
            nameInput.addEventListener('input', syncSlug);
            if (!slugInput.value) syncSlug(); // isi ulang setelah error validasi
        }
    }

    // Chip "+N" model: klik untuk memperlihatkan sisa model secara inline.
    document.querySelectorAll('[data-more-chips]').forEach(function (chip) {
        function expand() {
            var hidden = chip.nextElementSibling;
            if (!hidden || !hidden.hidden) return;
            hidden.hidden = false;
            chip.style.display = 'none';
        }
        chip.addEventListener('click', expand);
        chip.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                expand();
            }
        });
    });

    // Plan gratis: durasi jadi "tanpa masa berlaku" dan harga di-nol-kan otomatis.
    document.querySelectorAll('input[name="resets_daily"]').forEach(function (cb) {
        var form = cb.closest('form');
        var duration = form.querySelector('select[name="duration_hours"]');
        var usd = form.querySelector('input[name="price_usd"]');
        var idr = form.querySelector('input[name="price_idr"]');
        function sync() {
            if (!cb.checked) return;
            if (duration) duration.value = '';
            if (usd) usd.value = '0';
            if (idr) idr.value = '0';
            var stock = form.querySelector('input[name="stock"]');
            if (stock) stock.value = '';
        }
        cb.addEventListener('change', sync);
        sync(); // saat edit plan gratis, form langsung konsisten
    });
})();
</script>
@endsection

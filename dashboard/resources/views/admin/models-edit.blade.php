@extends('layouts.app')

@section('content')
<style>
    .price-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .price-box{background:var(--soft);border:1px solid var(--line);border-radius:8px;padding:8px 10px}
    .price-box .lbl{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px}
    .price-box .val{font-size:13px;font-weight:750;font-variant-numeric:tabular-nums}
    .badge.promo{background:var(--red);border-color:var(--red);color:#fff;font-weight:800;letter-spacing:.04em}
    .idr-hint{display:block;margin:-7px 0 10px;font-size:11px;font-weight:600;color:var(--green-ink);font-variant-numeric:tabular-nums}
    .promo-fields{padding:10px 12px 0;margin-bottom:10px;background:var(--red-soft);border:1px solid var(--red-line);border-radius:10px}
    .discount-presets{display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:8px}
    .discount-preset{padding:5px 9px;font-size:12px}.discount-preset.active{background:var(--red);border-color:var(--red);color:#fff}
    .discount-custom{display:flex;align-items:center;gap:5px;margin:0;color:var(--muted);font-size:12px}.discount-custom input{width:76px;margin:0;padding:5px 8px}
    @media(max-width:639.98px){.price-grid{grid-template-columns:1fr}}
</style>

<div class="top">
    <div><h2>Edit Model</h2><p>Perbarui detail model <strong style="font-family:ui-monospace,Menlo,Consolas,monospace">{{ $model->public_name }}</strong>.</p></div>
    <span class="pill">#{{ $model->id }}</span>
</div>

@include('admin._alerts')

<div class="section card" style="max-width:720px">
    <form method="post" action="{{ route('admin.models.update', $model) }}" enctype="multipart/form-data" class="grid2" style="gap:0 22px">
        @csrf @method('patch')
        <div>
            <label class="muted">Provider</label>
            <select name="provider_id"><option value="">Default env provider</option>@foreach($providers as $provider)<option value="{{ $provider->id }}" @selected($model->provider_id === $provider->id)>{{ $provider->name }}</option>@endforeach</select>
            <label class="muted">Public Name</label><input name="public_name" value="{{ old('public_name', $model->public_name) }}" required>
            <label class="muted">Upstream Name</label><input name="upstream_name" value="{{ old('upstream_name', $model->upstream_name) }}" required>
            <label class="muted">Ikon Model</label>
            <div style="display:flex;align-items:center;gap:10px;margin:4px 0 2px">
                @if($model->icon_url)
                    <img src="{{ $model->icon_url }}" alt="Ikon {{ $model->public_name }}" style="width:36px;height:36px;border-radius:9px;object-fit:contain;border:1px solid var(--line);background:var(--soft)">
                @endif
                <input name="icon" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="padding:6px;margin:0">
            </div>
            <p class="muted" style="font-size:11.5px;margin:2px 0 0">PNG/JPG/SVG/WebP, maks 5 MB. File baru menggantikan ikon lama.</p>
            @if($model->icon_url)
            <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted);cursor:pointer;margin:4px 0 0"><input type="checkbox" name="remove_icon" value="1" style="width:15px;height:15px;margin:0;padding:0;flex:0 0 auto"> Hapus ikon (kembali ke ikon default)</label>
            @endif
            @error('icon')<div style="color:var(--red-ink);font-size:12.5px;margin-top:4px">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="muted">Kemampuan (multi-modal)</label>
            @php($caps = collect($model->capabilities ?: [strtolower((string) $model->type)])->map(fn ($c) => strtolower((string) $c))->unique()->values())
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px 14px;margin:4px 0 10px">
                @foreach(capability_options() as $val => $label)
                <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted);cursor:pointer"><input type="checkbox" name="capabilities[]" value="{{ $val }}" @checked($caps->contains($val)) style="width:15px;height:15px;margin:0;padding:0;flex:0 0 auto"> {{ $label }}</label>
                @endforeach
            </div>
            <label class="muted">Context Window</label><input name="context_window" type="number" min="1" placeholder="128000" value="{{ old('context_window', $model->context_window) }}">
            <label class="muted">Rate Limit Per Menit (kosong = tanpa batas)</label><input name="rate_limit_per_minute" type="number" min="1" placeholder="60" value="{{ old('rate_limit_per_minute', $model->rate_limit_per_minute) }}">
            <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted);cursor:pointer;margin:2px 0 0"><input type="checkbox" name="is_active" value="1" @checked($model->is_active) style="width:15px;height:15px;margin:0;padding:0;flex:0 0 auto"> Aktif — model bisa dipakai user</label>
            <div style="display:flex;gap:8px;margin-top:14px">
                <button>Simpan Perubahan</button>
                <a class="btn secondary" href="{{ route('admin.models') }}" style="text-decoration:none">Batal</a>
            </div>
        </div>
    </form>
</div>

<div class="section card" style="max-width:720px">
    <h3>Pricing & Promo</h3>
    <form method="post" action="{{ route('admin.models.pricing', $model) }}">@csrf
        <div class="form-row">
            <div><label class="muted">Harga Normal Input / 1M (USD)</label><input id="edit-p-in" name="input_per_million" type="number" min="0" step="0.0001" value="{{ old('input_per_million', $normalInputPrice) }}" required><small class="idr-hint" id="hint-edit-p-in">≈ Rp 0</small></div>
            <div><label class="muted">Harga Normal Output / 1M (USD)</label><input id="edit-p-out" name="output_per_million" type="number" min="0" step="0.0001" value="{{ old('output_per_million', $normalOutputPrice) }}" required><small class="idr-hint" id="hint-edit-p-out">≈ Rp 0</small></div>
        </div>
        <div class="form-row">
            <div><label class="muted">Cache Read / 1M (USD)</label><input id="edit-p-cr" name="cache_read_input_per_million" type="number" min="0" step="0.0001" value="{{ old('cache_read_input_per_million', $pricingRule?->cache_read_input_per_million) }}"><small class="idr-hint" id="hint-edit-p-cr">≈ Rp 0</small></div>
            <div><label class="muted">Cache Write / 1M (USD)</label><input id="edit-p-cw" name="cache_write_per_million" type="number" min="0" step="0.0001" value="{{ old('cache_write_per_million', $pricingRule?->cache_write_per_million) }}"><small class="idr-hint" id="hint-edit-p-cw">≈ Rp 0</small></div>
        </div>
        <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted);cursor:pointer;margin:0 0 8px"><input id="edit-promo-toggle" type="checkbox" name="is_promo" value="1" @checked(old('is_promo', $pricingRule?->is_promo)) style="width:15px;height:15px;margin:0"> Aktifkan harga promo</label>
        <div id="edit-promo-fields" class="promo-fields" hidden>
            <div class="discount-presets">@foreach([5, 10, 15, 20, 25, 30, 50] as $discount)<button class="secondary discount-preset" type="button" data-discount="{{ $discount }}">{{ $discount }}%</button>@endforeach <label class="discount-custom">Custom <input id="edit-discount" type="number" min="0" max="100" step="0.01" placeholder="0"> %</label></div>
            <div class="form-row">
                <div><label class="muted">Mulai Promo (WIB)</label><input id="edit-promo-starts-at" name="promo_starts_at" type="datetime-local" value="{{ old('promo_starts_at', $pricingRule?->promo_starts_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')) }}"></div>
                <div><label class="muted">Berakhir Promo (WIB)</label><input id="edit-promo-ends-at" name="promo_ends_at" type="datetime-local" value="{{ old('promo_ends_at', $pricingRule?->promo_ends_at?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')) }}"></div>
            </div>
            <div class="form-row">
                <div><label class="muted">Harga Promo Input / 1M (USD)</label><input id="edit-promo-in" name="promo_input_per_million" type="number" min="0" step="0.0001" value="{{ old('promo_input_per_million', $pricingRule?->is_promo ? $pricingRule->input_per_million : null) }}"><small class="idr-hint" id="hint-edit-promo-in">≈ Rp 0</small></div>
                <div><label class="muted">Harga Promo Output / 1M (USD)</label><input id="edit-promo-out" name="promo_output_per_million" type="number" min="0" step="0.0001" value="{{ old('promo_output_per_million', $pricingRule?->is_promo ? $pricingRule->output_per_million : null) }}"><small class="idr-hint" id="hint-edit-promo-out">≈ Rp 0</small></div>
            </div>
        </div>
        <p class="muted" style="font-size:12px">Menyimpan pricing akan membuat rule baru; histori rule sebelumnya tetap tersimpan.</p>
        <button type="submit">Simpan Pricing</button>
    </form>
</div>

<div class="section card" style="max-width:720px;border-color:var(--red-line)">
    <h3 style="color:var(--red-ink)">Zona Bahaya</h3>
    <p class="muted" style="font-size:13px;margin:0 0 12px">Menghapus model <strong>{{ $model->public_name }}</strong> akan menghapus semua pricing rule-nya. Request historis di usage log tetap tersimpan.</p>
    <form method="post" action="{{ route('admin.models.destroy', $model) }}" onsubmit="return confirm('Hapus model {{ $model->public_name }}? Semua pricing rule-nya ikut terhapus.')">@csrf @method('delete')<button class="danger">Hapus Model</button></form>
</div>
<script>
(function () {
    var rate = {{ $usdRate }};
    var fields = [['edit-p-in','hint-edit-p-in'],['edit-p-out','hint-edit-p-out'],['edit-p-cr','hint-edit-p-cr'],['edit-p-cw','hint-edit-p-cw'],['edit-promo-in','hint-edit-promo-in'],['edit-promo-out','hint-edit-promo-out']];
    var toggle = document.getElementById('edit-promo-toggle');
    var promoFields = document.getElementById('edit-promo-fields');
    var discount = document.getElementById('edit-discount');
    var normalIn = document.getElementById('edit-p-in');
    var normalOut = document.getElementById('edit-p-out');
    var promoIn = document.getElementById('edit-promo-in');
    var promoOut = document.getElementById('edit-promo-out');
    var promoStartsAt = document.getElementById('edit-promo-starts-at');
    var promoEndsAt = document.getElementById('edit-promo-ends-at');
    var presets = Array.prototype.slice.call(document.querySelectorAll('.discount-preset'));

    function fmtIDR(value) {
        var idr = value * rate;
        if (!value) return 'Rp 0';
        if (idr >= 10000) return 'Rp ' + idr.toLocaleString('id-ID', {maximumFractionDigits:0});
        if (idr >= 1) return 'Rp ' + idr.toLocaleString('id-ID', {minimumFractionDigits:2,maximumFractionDigits:2});
        return 'Rp ' + idr.toLocaleString('id-ID', {maximumFractionDigits:4});
    }
    fields.forEach(function (pair) {
        var input = document.getElementById(pair[0]); var hint = document.getElementById(pair[1]);
        function update() { var value = parseFloat(input.value); hint.textContent = isNaN(value) ? '≈ Rp 0' : '≈ ' + fmtIDR(value); }
        input.addEventListener('input', update); update();
    });
    function syncPromo() {
        var active = toggle.checked; promoFields.hidden = !active; promoIn.required = active; promoOut.required = active; promoStartsAt.required = active; promoEndsAt.required = active;
    }
    function calculate(value) {
        value = Math.max(0, Math.min(100, parseFloat(value) || 0)); var factor = (100 - value) / 100;
        [[normalIn,promoIn],[normalOut,promoOut]].forEach(function (pair) { var normal = parseFloat(pair[0].value); if (!isNaN(normal)) { pair[1].value = (normal * factor).toFixed(4); pair[1].dispatchEvent(new Event('input')); } });
        presets.forEach(function (button) { button.classList.toggle('active', parseFloat(button.dataset.discount) === value); });
    }
    presets.forEach(function (button) { button.addEventListener('click', function () { discount.value = button.dataset.discount; calculate(button.dataset.discount); }); });
    discount.addEventListener('input', function () { calculate(this.value); });
    [normalIn,normalOut].forEach(function (input) { input.addEventListener('input', function () { if (toggle.checked && discount.value !== '') calculate(discount.value); }); });
    toggle.addEventListener('change', syncPromo); syncPromo();
})();
</script>
@endsection

@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>Pricing</h2><p>Harga per model sudah final (USD per 1 juta token) — tanpa margin.</p></div>
    <span class="pill">{{ $pricingRules->count() }} rules</span>
</div>

@include('admin._alerts')

<div class="section card">
    <h3>Add Pricing</h3>
    <form method="post" action="{{ route('admin.pricing.store') }}" class="grid2" style="gap:0 22px">
        @csrf
        <div>
            <label class="muted">Model</label><select name="ai_model_id" required>@foreach($models as $model)<option value="{{ $model->id }}">{{ $model->public_name }}</option>@endforeach</select>
            <div class="form-row"><div><label class="muted">Harga Normal Input / 1M (USD)</label><input id="p-in" name="input_per_million" type="number" step="0.0001" min="0" placeholder="0.18" required><small class="idr-hint" id="hint-p-in">≈ Rp 0</small></div><div><label class="muted">Harga Normal Output / 1M (USD)</label><input id="p-out" name="output_per_million" type="number" step="0.0001" min="0" placeholder="0.32" required><small class="idr-hint" id="hint-p-out">≈ Rp 0</small></div></div>
        </div>
        <div>
            <div class="form-row">
                <div><label class="muted">Cache Read / 1M (USD) <span title="Harga input saat token dilayani dari cache provider (biasanya lebih murah). Kosongkan jika tidak ada diskon.">ⓘ</span></label><input id="p-cr" name="cache_read_input_per_million" type="number" step="0.0001" min="0" placeholder="0.09"><small class="idr-hint" id="hint-p-cr">≈ Rp 0</small></div>
                <div><label class="muted">Cache Write / 1M (USD) <span title="Harga per token yang ditulis ke cache provider (cache creation). Kosongkan jika tidak ada biaya.">ⓘ</span></label><input id="p-cw" name="cache_write_per_million" type="number" step="0.0001" min="0" placeholder="0.18"><small class="idr-hint" id="hint-p-cw">≈ Rp 0</small></div>
            </div>
            <label style="display:flex;align-items:center;gap:7px;font-size:12.5px;color:var(--muted);cursor:pointer;margin:0 0 8px"><input id="promo-toggle" type="checkbox" name="is_promo" value="1" @checked(old('is_promo')) style="width:15px;height:15px;margin:0;padding:0;flex:0 0 auto"> Aktifkan harga promo</label>
            <div id="promo-fields" class="promo-fields" hidden>
                <label class="muted">Diskon Cepat</label>
                <div class="discount-tools">
                    <div class="discount-presets" role="group" aria-label="Pilihan diskon cepat">
                        @foreach([5, 10, 15, 20, 25, 30, 50] as $discount)<button class="secondary discount-preset" type="button" data-discount="{{ $discount }}">{{ $discount }}%</button>@endforeach
                    </div>
                    <label class="discount-custom"><span>Custom</span><input id="promo-discount" type="number" min="0" max="100" step="0.01" value="" placeholder="0"><span>%</span></label>
                </div>
                <small class="muted discount-note">Harga promo dihitung otomatis dari harga normal dan tetap bisa diedit manual.</small>
                <div class="form-row">
                    <div><label class="muted">Mulai Promo (WIB)</label><input id="promo-starts-at" name="promo_starts_at" type="datetime-local" value="{{ old('promo_starts_at') }}"></div>
                    <div><label class="muted">Berakhir Promo (WIB)</label><input id="promo-ends-at" name="promo_ends_at" type="datetime-local" value="{{ old('promo_ends_at') }}"></div>
                </div>
                <div class="form-row">
                    <div><label class="muted">Harga Promo Input / 1M (USD)</label><input id="p-promo-in" name="promo_input_per_million" type="number" step="0.0001" min="0" value="{{ old('promo_input_per_million') }}" placeholder="0.09"><small class="idr-hint" id="hint-p-promo-in">≈ Rp 0</small></div>
                    <div><label class="muted">Harga Promo Output / 1M (USD)</label><input id="p-promo-out" name="promo_output_per_million" type="number" step="0.0001" min="0" value="{{ old('promo_output_per_million') }}" placeholder="0.16"><small class="idr-hint" id="hint-p-promo-out">≈ Rp 0</small></div>
                </div>
            </div>
            <p class="muted" style="font-size:12px;margin:0 0 10px">Currency: <strong>USD</strong> — harga promo dipakai untuk billing, sedangkan harga normal ditampilkan dicoret. Cache Redis gateway selalu aktif (global).</p>
            <button>Save Pricing</button>
        </div>
    </form>
</div>

<div class="section card">
    <h3>Pricing Rules</h3>
    <div class="table-wrap"><table><tr><th>Model</th><th>Input / 1M</th><th>Output / 1M</th><th>Cache Read / 1M</th><th>Cache Write / 1M</th><th>Promo</th><th>Status</th><th>Created</th></tr>
        @forelse($pricingRules as $rule)
        <tr>
            <td><strong>{{ $rule->aiModel?->public_name ?? '—' }}</strong></td>
            <td>@if($rule->is_promo && $rule->original_input_per_million !== null)<span class="price-original">{{ format_usd($rule->original_input_per_million) }}</span>@endif{{ format_usd($rule->input_per_million) }}</td>
            <td>@if($rule->is_promo && $rule->original_output_per_million !== null)<span class="price-original">{{ format_usd($rule->original_output_per_million) }}</span>@endif{{ format_usd($rule->output_per_million) }}</td>
            <td>{{ $rule->cache_read_input_per_million !== null ? format_usd($rule->cache_read_input_per_million) : '—' }}</td>
            <td>{{ $rule->cache_write_per_million !== null ? format_usd($rule->cache_write_per_million) : '—' }}</td>
            <td>@if($rule->is_promo)<span class="badge promo">PROMO</span>@else<span class="muted">—</span>@endif</td>
            <td><span class="badge {{ $rule->is_active ? 'green' : 'red' }}">{{ $rule->is_active ? 'active' : 'off' }}</span></td>
            <td>{{ $rule->created_at?->format('d M Y H:i') }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--muted)">Belum ada pricing rule — tambahkan di atas.</td></tr>
        @endforelse
    </table></div>
</div>

<style>
    .idr-hint{display:block;margin:-7px 0 10px;font-size:11px;font-weight:600;color:var(--green-ink);font-variant-numeric:tabular-nums}
    .badge.promo{background:var(--red);border-color:var(--red);color:#fff;font-weight:800;letter-spacing:.04em}
    .promo-fields{padding:10px 12px 0;margin-bottom:10px;background:var(--red-soft);border:1px solid var(--red-line);border-radius:10px}
    .discount-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:4px 0 5px}
    .discount-presets{display:flex;gap:5px;flex-wrap:wrap}
    .discount-preset{padding:5px 9px;font-size:12px}
    .discount-preset.active{background:var(--red);border-color:var(--red);color:#fff}
    .discount-custom{display:flex;align-items:center;gap:5px;margin:0;color:var(--muted);font-size:12px}
    .discount-custom input{width:76px;margin:0;padding:5px 8px}
    .discount-note{display:block;margin-bottom:10px}
    .price-original{text-decoration:line-through;color:var(--muted);font-size:11px;display:block}
    @media(max-width:639.98px){.discount-tools{align-items:flex-start;flex-direction:column}.discount-preset{min-height:34px}}
</style>

<script>
(function () {
    // Pasangan IDR realtime saat admin mengetik harga USD (kurs dari server).
    var rate = {{ $usdRate }};
    var fields = [['p-in', 'hint-p-in'], ['p-out', 'hint-p-out'], ['p-cr', 'hint-p-cr'], ['p-cw', 'hint-p-cw'], ['p-promo-in', 'hint-p-promo-in'], ['p-promo-out', 'hint-p-promo-out']];
    var promoToggle = document.getElementById('promo-toggle');
    var promoFields = document.getElementById('promo-fields');
    var promoInputs = [document.getElementById('p-promo-in'), document.getElementById('p-promo-out'), document.getElementById('promo-starts-at'), document.getElementById('promo-ends-at')];

    function syncPromo() {
        var active = promoToggle && promoToggle.checked;
        if (promoFields) promoFields.hidden = !active;
        promoInputs.forEach(function (input) { if (input) input.required = active; });
    }
    var discountInput = document.getElementById('promo-discount');
    var normalInput = document.getElementById('p-in');
    var normalOutput = document.getElementById('p-out');
    var promoInput = document.getElementById('p-promo-in');
    var promoOutput = document.getElementById('p-promo-out');
    var presetButtons = Array.prototype.slice.call(document.querySelectorAll('.discount-preset'));

    function calculatePromo(discount) {
        discount = Math.max(0, Math.min(100, parseFloat(discount) || 0));
        var factor = (100 - discount) / 100;
        [[normalInput, promoInput], [normalOutput, promoOutput]].forEach(function (pair) {
            var normal = parseFloat(pair[0].value);
            if (isNaN(normal)) return;
            pair[1].value = (normal * factor).toFixed(4);
            pair[1].dispatchEvent(new Event('input', { bubbles: true }));
        });
        presetButtons.forEach(function (button) { button.classList.toggle('active', parseFloat(button.dataset.discount) === discount); });
    }
    presetButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            discountInput.value = button.dataset.discount;
            calculatePromo(button.dataset.discount);
        });
    });
    if (discountInput) discountInput.addEventListener('input', function () { calculatePromo(this.value); });
    [normalInput, normalOutput].forEach(function (input) {
        input.addEventListener('input', function () { if (promoToggle.checked && discountInput.value !== '') calculatePromo(discountInput.value); });
    });
    if (promoToggle) promoToggle.addEventListener('change', syncPromo);
    syncPromo();

    function fmtIDR(v) {
        if (!v) return 'Rp 0';
        var idr = v * rate;
        if (idr >= 10000) return 'Rp ' + idr.toLocaleString('id-ID', { maximumFractionDigits: 0 });
        if (idr >= 1) return 'Rp ' + idr.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return 'Rp ' + idr.toLocaleString('id-ID', { maximumFractionDigits: 4 });
    }

    fields.forEach(function (pair) {
        var input = document.getElementById(pair[0]);
        var hint = document.getElementById(pair[1]);
        if (!input || !hint) return;
        function update() {
            var v = parseFloat(input.value);
            hint.textContent = isNaN(v) ? '≈ Rp 0' : '≈ ' + fmtIDR(v);
        }
        input.addEventListener('input', update);
        update();
    });
})();
</script>
@endsection

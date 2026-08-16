@extends('layouts.app')

@section('content')
<style>
    .st-hero{display:flex;align-items:center;gap:14px;border-radius:16px;padding:20px 24px;margin-bottom:18px;border:1px solid var(--line)}
    .st-hero .dot{width:14px;height:14px;border-radius:50%;flex:0 0 auto}
    .st-hero.green{background:var(--green-soft);border-color:var(--green-line)}
    .st-hero.green .dot{background:var(--green)}
    .st-hero.amber{background:var(--amber-soft);border-color:var(--amber-line)}
    .st-hero.amber .dot{background:var(--amber);animation:pulse 1.6s ease-in-out infinite}
    .st-hero.red{background:var(--red-soft);border-color:var(--red-line)}
    .st-hero.red .dot{background:var(--red);animation:pulse 1.2s ease-in-out infinite}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
    .st-hero h2{margin:0;font-size:18px;font-weight:800;letter-spacing:-.02em}
    .st-hero p{margin:2px 0 0;font-size:13px;color:var(--muted)}
    .st-hero .right{margin-left:auto;display:flex;align-items:center;gap:14px;flex-shrink:0}
    .st-hero .check{text-align:right;font-size:12px;color:var(--muted);line-height:1.4}
    .st-hero .check strong{display:block;font-size:13.5px;color:var(--body);font-variant-numeric:tabular-nums;font-weight:750}

    .st-toolbar{display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap}
    .st-search{position:relative;flex:1;min-width:200px}
    .st-search svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted);pointer-events:none}
    .st-search input{padding-left:32px;margin:0}
    .st-chips{display:flex;gap:6px;flex-wrap:wrap}
    .st-chips .chip{display:inline-flex;align-items:center;gap:6px;background:var(--panel);border:1px solid var(--line);color:var(--body);border-radius:999px;padding:5px 12px;font-size:12px;font-weight:650;cursor:pointer;transition:border-color .13s,color .13s,background .13s,transform .1s}
    .st-chips .chip:hover{border-color:var(--line-strong);color:var(--ink)}
    .st-chips .chip:active{transform:scale(.97)}
    .st-chips .chip.active{background:var(--brand);border-color:var(--brand);color:#fff}
    .st-chips .chip .n{display:inline-flex;align-items:center;justify-content:center;min-width:17px;height:17px;padding:0 5px;border-radius:999px;background:var(--soft);color:var(--muted);font-size:10.5px;font-weight:700}
    .st-chips .chip.active .n{background:rgba(255,255,255,.22);color:#fff}

    .st-list{display:grid;gap:4px}
    .st-card{background:var(--panel);border:1px solid var(--line);border-radius:9px;padding:6px 10px;box-shadow:var(--shadow-card)}
    .st-head{display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap}
    .st-dot{width:8px;height:8px;border-radius:50%;margin-top:4px;flex:0 0 auto}
    .st-dot.green{background:var(--green)}
    .st-dot.amber{background:var(--amber)}
    .st-dot.red{background:var(--red)}
    .st-dot.gray{background:#cbd5e1}
    .st-name{min-width:0;flex:1}
    .st-name strong{font-size:12.5px;font-weight:750;letter-spacing:-.01em;word-break:break-all}
    .st-name .up{font-size:10.5px;color:var(--muted);font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:400}
    .st-badges{display:flex;gap:3px;margin-top:3px;flex-wrap:wrap;align-items:center}
    .st-badges .badge svg{width:9px;height:9px;display:block}
    .st-badges .badge{padding:1px 5px;font-size:10px}
    .st-stats{display:flex;gap:12px;flex-shrink:0}
    .st-num .lbl{font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
    .st-num .val{font-size:13px;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.01em}
    .st-num .sub{display:block;font-size:9.5px;color:var(--muted);margin-top:1px}

    .tl{margin-top:5px;padding-top:5px;border-top:1px dashed var(--line)}
    .tl-scroll{overflow-x:auto}
    .tl-cells{display:grid;grid-template-columns:repeat(30,minmax(7px,1fr));gap:2px;min-width:440px}
    .tl-dates{display:grid;grid-template-columns:repeat(30,minmax(7px,1fr));gap:2px;margin-top:2px;min-width:440px}
    .tl-dates span{font-size:7.5px;color:#94a3b8;visibility:hidden;text-align:center;font-variant-numeric:tabular-nums;line-height:1}
    .tl-dates span.show{visibility:visible}
    .cell{height:8px;border-radius:2px;background:var(--soft);border:1px solid var(--line)}
    .cell.green{background:var(--green);border-color:var(--green)}
    .cell.amber{background:var(--amber);border-color:var(--amber)}
    .cell.red{background:var(--red);border-color:var(--red)}
    .cell.gray{background:var(--soft);border-color:var(--line)}
    .tl-legend i.g{background:var(--green)} .tl-legend i.a{background:var(--amber)} .tl-legend i.r{background:var(--red)} .tl-legend i.n{background:var(--soft);border:1px solid var(--line)}

    @media (max-width:899.98px){
        .st-stats{width:100%;justify-content:space-between;gap:10px}
        .st-hero{flex-wrap:wrap}
        .st-hero .right{margin-left:0}
        .st-hero .check{text-align:left}
    }
    @media (max-width:639.98px){ /* Phone */
        .st-hero{padding:14px 16px;gap:9px}
        .st-hero .right{width:100%;justify-content:space-between}
        .st-hero h2{font-size:15.5px}
        .st-hero p{font-size:12px}
        .st-card{padding:6px 9px}
        .st-head{flex-direction:column;align-items:stretch;gap:5px}
        .st-dot{align-self:flex-start}
        .st-name strong{font-size:12.5px}
        .st-stats{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:6px;justify-content:stretch}
        .st-num .val{font-size:13px}
        .tl{margin-top:5px;padding-top:5px}
        .tl-scroll{overflow-x:hidden}
        .tl-cells{grid-template-columns:repeat(30,minmax(0,1fr));gap:2px;min-width:0}
        .tl-dates{display:none}
        .cell{height:7px;border-radius:2px}
    }
</style>

<div class="st-hero {{ $overall[0] }}">
    <span class="dot"></span>
    <div>
        <h2>{{ $overall[1] }}</h2>
        <p>{{ $models->where('is_active', true)->count() }} dari {{ $models->count() }} model aktif · {{ number_format($totalRequests) }} requests · {{ $totalErrors }} errors</p>
    </div>
    <div class="right">
        <div class="check">
            <strong>{{ $liveCheckedAt ? \Illuminate\Support\Carbon::parse($liveCheckedAt)->format('d M Y H:i:s') : '—' }}</strong>
            cek terakhir · otomatis setiap 10 menit
        </div>
    </div>
</div>

@if($liveError)
    <div style="background:var(--red-soft);border:1px solid var(--red-line);color:var(--red-ink);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px">⚠️ Ping realtime tidak tersedia: {{ $liveError }} — status ditampilkan dari data usage.</div>
@endif

<div class="st-toolbar">
    <div class="st-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
        <input id="st-search" type="search" placeholder="Cari model..." autocomplete="off" aria-label="Cari model">
    </div>
    <div class="st-chips" role="group" aria-label="Filter status">
        <button class="chip active" data-st="all" aria-pressed="true">Semua <span class="n"></span></button>
        <button class="chip" data-st="Operational" aria-pressed="false">Operational <span class="n"></span></button>
        <button class="chip" data-st="Degraded" aria-pressed="false">Degraded <span class="n"></span></button>
        <button class="chip" data-st="Down" aria-pressed="false">Down <span class="n"></span></button>
        <button class="chip" data-st="No pricing" aria-pressed="false">No pricing <span class="n"></span></button>
        <button class="chip" data-st="Off" aria-pressed="false">Off <span class="n"></span></button>
    </div>
</div>

<div class="st-list">
    @forelse($models as $model)
        @php
            $s = $stats->get($model->public_name);
            $daily = $daily->get($model->public_name, collect());
            $requests = (int) ($s->requests ?? 0);
            $errors = (int) ($s->errors ?? 0);
            $uptime = $requests > 0 ? round(($requests - $errors) / $requests * 100, 2) : null;
            $avgLatency = $s?->avg_latency !== null ? (float) $s->avg_latency : null;
            $hasPricing = (bool) $model->latestPricingRule;
            $ping = $live?->get($model->public_name);
            $pingOk = $ping && (bool) ($ping['reachable'] ?? false);
            $pingLatency = $ping ? (int) ($ping['latency_ms'] ?? 0) : null;

            if (! $model->is_active) { $stColor = 'gray'; $stLabel = 'Off'; }
            elseif (! $hasPricing) { $stColor = 'red'; $stLabel = 'No pricing'; }
            elseif ($live !== null && ! $pingOk) { $stColor = 'red'; $stLabel = 'Down'; }
            elseif ($errors > 0) { $stColor = 'amber'; $stLabel = 'Degraded'; }
            elseif ($requests === 0) { $stColor = 'gray'; $stLabel = 'No data'; }
            else { $stColor = 'green'; $stLabel = 'Operational'; }

            $latStr = $pingLatency !== null
                ? ($pingLatency >= 1000 ? round($pingLatency / 1000, 2).'s' : $pingLatency.'ms')
                : ($avgLatency === null ? '—' : ($avgLatency >= 1000 ? round($avgLatency / 1000, 2).'s' : round($avgLatency).'ms'));
            $latSub = $s ? 'rata-rata usage' : 'belum pernah';
            $uptimeSub = $requests > 0 ? number_format($requests).' requests' : 'belum ada';
        @endphp
        <div class="st-card" data-status="{{ $stLabel }}" data-name="{{ strtolower($model->public_name) }}">
            <div class="st-head">
                <span class="st-dot {{ $stColor }}" title="{{ $stLabel }}"></span>
                <div class="st-name">
                    @php
                        // Upstream hanya ditampilkan jika berbeda dari alias: bukan sama persis,
                        // bukan substring satu sama lain (mis. alias deepseek/x vs upstream x).
                        $pub = strtolower((string) $model->public_name);
                        $up = strtolower((string) ($model->upstream_name ?? ''));
                        $showUp = $up !== '' && $up !== $pub && ! str_contains($pub, $up) && ! str_contains($up, $pub);
                    @endphp
                    <strong>{{ $model->public_name }} @if($showUp)<span class="up">· {{ $model->upstream_name }}</span>@endif</strong>
                    <div class="st-badges">
                        @foreach(collect($model->capabilities ?: [strtolower((string) $model->type)])->unique() as $cap)
                            <span class="badge" title="{{ $cap }}" style="padding:3px 5px;line-height:0">{!! capability_icon($cap) !!}</span>
                        @endforeach
                        <span class="badge {{ $stColor === 'green' ? 'green' : ($stColor === 'red' ? 'red' : ($stColor === 'amber' ? 'amber' : '')) }}">{{ $stLabel }}</span>
                        @if($pingLatency !== null)<span class="badge {{ $pingOk ? 'green' : 'red' }}">{{ $pingOk ? '●' : '✕' }} {{ $latStr }}</span>@endif
                    </div>
                </div>
                <div class="st-stats">
                    <div class="st-num">
                        <span class="lbl">Uptime</span>
                        <div class="val">{{ $uptime !== null ? $uptime.'%' : '—' }}</div>
                        <span class="sub">{{ $uptimeSub }}</span>
                    </div>
                    <div class="st-num">
                        <span class="lbl">Latency</span>
                        <div class="val">{{ $latStr }}</div>
                        <span class="sub">{{ $latSub }}</span>
                    </div>
                </div>
            </div>
            <div class="tl">
                <div class="tl-scroll">
                    <div class="tl-cells" aria-label="Riwayat 30 hari terakhir">
                        @foreach($days as $day)
                            @php
                                $d = $daily->firstWhere('bucket', $day->format('Y-m-d H:i'));
                                $cell = 'gray';
                                if ($d && (int) $d->requests > 0) { $cell = (int) $d->errors > 0 ? 'amber' : 'green'; }
                                if (! $model->is_active) { $cell = 'gray'; }
                            @endphp
                            <span class="cell {{ $cell }}" title="{{ $day->format('H:i') }}–{{ $day->copy()->addMinutes(10)->format('H:i') }}: {{ $d ? $d->requests.' req'.($d->errors > 0 ? ' / '.$d->errors.' err' : '') : 'no request' }}"></span>
                        @endforeach
                    </div>
                    <div class="tl-dates">
                        @foreach($days as $day)
                            <span class="{{ $loop->index % 5 === 0 ? 'show' : '' }}">{{ $day->format('H:i') }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card empty-state" style="text-align:center;padding:44px 24px">
            <h3 style="margin:0 0 4px;font-size:15px">Tidak ada model terdaftar</h3>
            <p style="margin:0;color:var(--muted);font-size:13.5px">Tambahkan model di menu Models untuk melihat statusnya.</p>
        </div>
    @endforelse
</div>

<div id="st-empty" style="display:none;text-align:center;padding:32px 16px;color:var(--muted);font-size:13.5px">Tidak ada model yang cocok dengan filter.</div>

<div class="tl-legend" style="margin-top:14px">
    <span>5 jam terakhir · per 10 menit — arahkan kursor ke kotak untuk detail</span>
    <span style="margin-left:auto"><span><i class="g"></i>OK</span><span><i class="a"></i>Error</span><span><i class="r"></i>Down</span>    <span><i class="n"></i>No request</span></span>
</div>

<script>
(function () {
    // Filter status: pencarian + chip status.
    var cards = Array.prototype.slice.call(document.querySelectorAll('.st-card'));
    var chips = Array.prototype.slice.call(document.querySelectorAll('.st-chips .chip'));
    var input = document.getElementById('st-search');
    var empty = document.getElementById('st-empty');
    var activeSt = 'all';

    function apply() {
        var q = (input ? input.value : '').trim().toLowerCase();
        var visible = 0;
        cards.forEach(function (card) {
            var okSt = activeSt === 'all' || card.getAttribute('data-status') === activeSt;
            var okQ = !q || (card.getAttribute('data-name') || '').indexOf(q) !== -1;
            var show = okSt && okQ;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (empty) empty.style.display = cards.length > 0 && visible === 0 ? '' : 'none';
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            activeSt = chip.getAttribute('data-st');
            chips.forEach(function (c) { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
            chip.classList.add('active');
            chip.setAttribute('aria-pressed', 'true');
            apply();
        });
    });

    // Hitung jumlah per status.
    chips.forEach(function (chip) {
        var st = chip.getAttribute('data-st');
        var n = st === 'all' ? cards.length : cards.filter(function (c) { return c.getAttribute('data-status') === st; }).length;
        var el = chip.querySelector('.n');
        if (el) el.textContent = n;
    });

    if (input) input.addEventListener('input', apply);
})();

    // Pengecekan otomatis setiap 10 menit (600.000 ms).
    setTimeout(function () { window.location.reload(); }, 600000);
</script>
@endsection

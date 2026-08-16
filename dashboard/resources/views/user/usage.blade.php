@extends('layouts.app')

@section('content')
<style>
    /* ===== Halaman Request Log ===== */
    .rl-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
    .stat{display:flex;align-items:center;gap:12px;padding:16px 18px}
    .stat-ic{width:38px;height:38px;border-radius:11px;background:var(--brand-soft);border:1px solid var(--brand-line);color:var(--brand);display:grid;place-items:center;flex:0 0 auto}
    .stat-ic svg{width:18px;height:18px}
    .stat-ic.ic-green{background:var(--green-soft);border-color:var(--green-line);color:var(--green-ink)}
    .stat-ic.ic-red{background:var(--red-soft);border-color:var(--red-line);color:var(--red-ink)}
    .stat-ic.ic-slate{background:var(--soft);border-color:var(--line);color:var(--muted)}
    .stat-lbl{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
    .stat .metric{margin-top:2px}
    .stat-sub{font-size:11.5px;color:var(--muted);margin-top:2px}

    .rl-filters{background:var(--panel);border:1px solid var(--line);border-radius:var(--r-card);padding:16px 18px;margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
    .rl-field{display:flex;flex-direction:column;gap:4px;min-width:150px;flex:1}
    .rl-field label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
    .rl-field input,.rl-field select{margin:0}
    .rl-actions{display:flex;gap:8px;align-items:center}

    /* ===== Grafik Usage ===== */
    .rl-charts{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;margin-bottom:16px;min-width:0}
    .rl-charts > *{min-width:0}
    .rl-chart{background:var(--panel);border:1px solid var(--line);border-radius:var(--r-card);padding:20px 20px 16px;box-shadow:var(--shadow-card)}
    .rl-chart h3{margin:0 0 4px;font-size:14.5px;font-weight:750;letter-spacing:-.01em}
    .rl-chart .sub{color:var(--muted);font-size:12px;margin:0 0 14px}
    .chart-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
    .chart-head h3{margin:0 0 4px;font-size:14.5px;font-weight:750;letter-spacing:-.01em}
    .chart-head .sub{color:var(--muted);font-size:12px;margin:0}
    .chart-tabs{display:inline-flex;background:var(--soft);border:1px solid var(--line);border-radius:8px;padding:2px;gap:2px;flex:0 0 auto}
    .chart-tab{border:0;background:transparent;color:var(--muted);font-size:11.5px;font-weight:650;padding:5px 12px;border-radius:6px;cursor:pointer;transition:background .13s,color .13s}
    .chart-tab:hover{color:var(--ink)}
    .chart-tab.active{background:var(--panel);color:var(--ink);box-shadow:0 1px 3px rgba(15,23,42,.12)}

    /* Plot: gridlines + area bar */
    .plot{position:relative;display:grid;grid-template-columns:46px minmax(0,1fr);gap:12px;height:220px;padding-top:8px}
    .plot-y{display:flex;flex-direction:column;justify-content:space-between;text-align:right;padding:0 0 28px;font-size:10px;color:var(--muted);font-variant-numeric:tabular-nums}
    .plot-y span{line-height:1}
    .plot-grid{position:relative;border-bottom:1px solid var(--line);border-left:1px solid var(--line);border-radius:10px 10px 0 0;background:linear-gradient(to top,var(--line) 0 1px,transparent 1px 25%,var(--line) 25% calc(25% + 1px),transparent calc(25% + 1px) 50%,var(--line) 50% calc(50% + 1px),transparent calc(50% + 1px) 75%,var(--line) 75% calc(75% + 1px),transparent calc(75% + 1px));overflow:visible}
    .plot-bars{position:absolute;inset:0;display:flex;align-items:flex-end;gap:6px;padding:0 8px}
    .bar-col{flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;position:relative}
    .bar-stack{width:70%;max-width:30px;position:relative;min-height:3px;cursor:default;transition:filter .15s,transform .15s}
    .bar-body{height:100%;display:flex;flex-direction:column;justify-content:flex-end;border-radius:7px 7px 3px 3px;overflow:hidden;box-shadow:0 4px 10px rgba(15,23,42,.10)}
    .bar-col:hover .bar-stack{filter:brightness(1.05);transform:translateY(-2px);transform-origin:bottom}
    .bar-seg{width:100%;min-height:1px}
    .bar-seg.in{background:linear-gradient(180deg,#3b82f6,#2563eb)}
    .bar-seg.out{background:linear-gradient(180deg,#38bdf8,#0284c7)}
    .bar-seg.cost{background:linear-gradient(180deg,#fbbf24,#d97706)}
    .bar-seg.req{background:linear-gradient(180deg,#60a5fa,#2563eb)}
    .bar-col .tip{position:absolute;bottom:calc(100% + 10px);left:50%;transform:translateX(-50%) translateY(3px);background:#0f172a;color:#f1f5f9;font-size:11px;font-weight:600;padding:8px 12px;border-radius:9px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s,transform .15s;z-index:20;line-height:1.55;box-shadow:0 10px 28px rgba(15,23,42,.28);text-align:left}
    .bar-col:first-child .tip,.bar-col:nth-child(2) .tip{left:0;transform:translateY(3px)}
    .bar-col:first-child:hover .tip,.bar-col:nth-child(2):hover .tip{transform:translateY(0)}
    .bar-col:nth-last-child(1) .tip,.bar-col:nth-last-child(2) .tip{left:auto;right:0;transform:translateY(3px)}
    .bar-col:nth-last-child(1):hover .tip,.bar-col:nth-last-child(2):hover .tip{transform:translateY(0)}
    .bar-col:hover .tip{opacity:1;transform:translateX(-50%) translateY(0)}
    .tip b{font-weight:750;color:#fff}
    .tip .dim{color:#94a3b8;font-weight:500}
    .bar-col .bl{position:absolute;top:100%;margin-top:7px;font-size:9.5px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .chart-legend{display:flex;gap:16px;margin-top:26px;flex-wrap:wrap}
    .chart-legend span{display:inline-flex;align-items:center;gap:7px;font-size:11.5px;color:var(--muted);font-weight:550}
    .chart-legend i{width:10px;height:10px;border-radius:3px;display:inline-block}
    .chart-empty{color:var(--muted);font-size:13px;padding:44px 10px;text-align:center;border:1px dashed var(--line);border-radius:10px;background:var(--bg)}
    .area-chart{position:relative;height:238px;margin-top:4px;isolation:isolate}
    .area-chart svg{width:100%;height:100%;display:block;overflow:visible}
    .area-grid{stroke:var(--line);stroke-width:1;stroke-dasharray:3 5}
    .area-axis-label{fill:var(--muted);font-size:10px;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
    .area-fill{fill:url(#usage-area-gradient)}
    .area-line{fill:none;stroke:#2563eb;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 3px 4px rgba(37,99,235,.18))}
    .area-point{fill:var(--panel);stroke:#2563eb;stroke-width:2;cursor:pointer;transition:r .15s,fill .15s}
    .area-hit{fill:transparent;cursor:crosshair}
    .area-hit:hover + .area-point{r:5;fill:#2563eb}
    .area-tooltip{position:absolute;z-index:10;min-width:150px;padding:9px 11px;border-radius:10px;background:#0f172a;color:#f8fafc;box-shadow:0 10px 28px rgba(15,23,42,.28);font-size:11px;line-height:1.55;pointer-events:none;opacity:0;transform:translate(-50%,-100%) translateY(-10px);transition:opacity .12s;white-space:nowrap}
    .area-tooltip.show{opacity:1}
    .area-tooltip strong{display:block;font-size:11.5px}
    .area-tooltip span{color:#94a3b8}
    .area-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:8px;padding-top:12px;border-top:1px solid var(--line);font-size:11.5px;color:var(--muted)}
    .area-summary strong{color:var(--ink);font-size:13px;font-variant-numeric:tabular-nums}

    /* Pemakaian per model: bar horizontal yang rapi */
    .rl-permodel{display:grid;gap:16px;margin-top:2px}
    .pm-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px 10px;align-items:center;font-size:12.5px;padding:10px 0;border-bottom:1px solid var(--line)}
    .pm-row:last-child{border-bottom:0}
    .pm-name{font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--ink)}
    .pm-val{font-variant-numeric:tabular-nums;color:var(--body);white-space:nowrap;font-size:12px;font-weight:700}
    .pm-track{grid-column:1/-1;height:11px;background:var(--soft);border:1px solid var(--line);border-radius:999px;overflow:hidden;box-shadow:inset 0 1px 2px rgba(15,23,42,.04)}
    .pm-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#2563eb,#38bdf8);transition:width .4s ease}
    .pm-fill.cost{background:linear-gradient(90deg,#fbbf24,#d97706)}
    .pm-meta{grid-column:1/-1;display:flex;gap:14px;flex-wrap:wrap;font-size:11px;color:var(--muted);margin-top:-2px}
    .pm-meta b{font-variant-numeric:tabular-nums;color:var(--body);font-weight:650}
    .pm-meta .pm-err{color:var(--red-ink)}
    .pm-meta .sep{color:var(--line-strong)}

    @media (max-width:899.98px){
        .rl-charts{grid-template-columns:minmax(0,1fr)}
        .chart-head{flex-direction:column;align-items:stretch}
        .chart-tabs{width:100%;display:grid;grid-auto-flow:column}
    }

    .rl-row{cursor:pointer}
    .rl-row td{transition:background .12s}
    .rl-row:hover td{background:#f0f7ff}
    .rl-row .hint{color:var(--muted);font-size:12px}
    .rl-empty{text-align:center;padding:36px 20px;color:var(--muted)}
    .rl-empty h3{margin:0 0 4px;font-size:15px;font-weight:750;color:var(--ink)}
    .rl-empty p{margin:0;font-size:13.5px}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}

    .modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:none;align-items:center;justify-content:center;padding:20px}
    .modal-backdrop.open{display:flex}
    .modal{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(15,23,42,.25);animation:fadeUp .18s ease}
    .modal.modal-wide{max-width:620px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
    .modal h3{margin:0 0 14px;font-size:16px;font-weight:800;letter-spacing:-.01em}
    .modal p{margin:0 0 20px;color:var(--muted);font-size:13.5px}
    .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
    .dl{display:grid;grid-template-columns:150px 1fr;gap:9px 14px;font-size:13px}
    .dl dt{color:var(--muted);font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.05em;padding-top:1px}
    .dl dd{margin:0;word-break:break-word}
    .dl dd .badge{vertical-align:middle}
    .dl .rid{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .dl .rid .mono{font-size:12px;background:var(--soft);border:1px solid var(--line);border-radius:7px;padding:2px 8px}

    @media (min-width:640px) and (max-width:1099.98px){
        .rl-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:639.98px){
        .main{width:100%;max-width:100%;min-width:0}
        .content{max-width:100%;min-width:0;overflow-x:hidden}
        .top,.rl-stats,.rl-charts,.rl-filters,.section{width:100%;max-width:100%;min-width:0}
        .rl-stats{grid-template-columns:minmax(0,1fr)}
        .rl-chart{padding:16px 14px 14px;max-width:100%;min-width:0;overflow:hidden}
        .area-chart{width:100%;max-width:100%;overflow:hidden}
        .area-chart svg{max-width:100%}
        .table-wrap{max-width:100%}
        .plot{grid-template-columns:38px minmax(0,1fr);gap:8px;height:190px}
        .plot-bars{gap:3px;padding:0 4px}
        .bar-stack{width:82%}
        .bar-col .bl{font-size:8.5px;transform:rotate(-35deg);transform-origin:top center;margin-top:10px}
        .chart-legend{margin-top:34px}
        .rl-field{min-width:100%}
        .dl{grid-template-columns:110px 1fr}
    }
</style>

<div class="top">
    <div><h2>{{ __('dashboard.pages.usage.heading') }}</h2><p>{{ __('dashboard.pages.usage.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.pages.usage.audit_log') }}</span>
</div>

<div class="rl-stats">
    <div class="card stat">
        <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20v-6"/><path d="M12 20V8"/><path d="M18 20v-10"/><path d="M3 20h18"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.common.requests') }}</div><div class="metric small">{{ number_format($totalRequests) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.common.tokens') }}</div><div class="metric small">{{ format_compact_number($totalTokens) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-slate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.common.cost') }}</div><div class="metric small">{{ format_idr_usd($totalCost) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.common.errors') }}</div><div class="metric small">{{ number_format($errorCount) }}</div><div class="stat-sub">{{ __('dashboard.pages.usage.avg_latency', ['value' => $avgLatency]) }}</div></div>
    </div>
</div>

<div class="rl-charts">
    <div class="rl-chart" data-chart="daily">
        <div class="chart-head">
            <div><h3>{{ __('dashboard.pages.usage.daily_activity') }}</h3><p class="sub">{{ __('dashboard.pages.usage.daily_hint') }}</p></div>
            <div class="chart-tabs" role="tablist" aria-label="{{ __('dashboard.pages.usage.chart_metric') }}">
                <button class="chart-tab active" data-metric="tokens" role="tab">{{ __('dashboard.common.tokens') }}</button>
                <button class="chart-tab" data-metric="cost" role="tab">{{ __('dashboard.common.cost') }}</button>
                <button class="chart-tab" data-metric="requests" role="tab">{{ __('dashboard.common.requests') }}</button>
            </div>
        </div>
        @php
            $yMaxT = $maxDailyTokens; $yMaxC = $maxDailyCost; $yMaxR = $maxDailyRequests;
            $fmtT = fn ($v) => $v >= 1000000 ? round($v/1000000,1).'M' : ($v >= 1000 ? round($v/1000,1).'K' : (string) (int) $v);
            $fmtC = fn ($v) => '$'.($v >= 1 ? number_format($v, 2) : number_format($v, 4));
        @endphp
        @if($daily->sum('tokens') > 0 || $daily->sum('requests') > 0)
            <div class="area-chart" id="usage-area-chart" data-points='@json($daily->values())' role="img" aria-label="{{ __('dashboard.pages.usage.area_aria') }}">
                <svg viewBox="0 0 640 238" preserveAspectRatio="none" aria-hidden="true"></svg>
                <div class="area-tooltip"></div>
            </div>
            <div class="area-summary">
                <span data-area-period>{{ __('dashboard.pages.usage.last_14_days') }}</span>
                <span>{{ __('dashboard.pages.usage.area_total_label') }}: <strong data-area-total>{{ format_compact_number($daily->sum('tokens')) }}</strong> {{ __('dashboard.pages.usage.unit_token') }}</span>
            </div>
        @else
            <div class="chart-empty">{{ __('dashboard.pages.usage.empty_14') }}</div>
        @endif
    </div>
    <div class="rl-chart" data-chart="model">
        <div class="chart-head">
            <div><h3>{{ __('dashboard.pages.usage.per_model') }}</h3><p class="sub">{{ __('dashboard.pages.usage.model_hint') }}</p></div>
            <div class="chart-tabs" role="tablist" aria-label="{{ __('dashboard.pages.usage.model_metric') }}">
                <button class="chart-tab active" data-metric="tokens" role="tab">{{ __('dashboard.common.tokens') }}</button>
                <button class="chart-tab" data-metric="cost" role="tab">{{ __('dashboard.common.cost') }}</button>
            </div>
        </div>
        @if($perModel->isNotEmpty())
            <div class="rl-permodel">
                @foreach($perModel as $row)
                    <div class="pm-row">
                        <span class="pm-name" title="{{ $row['model'] }}">{{ $row['model'] }}</span>
                        <span class="pm-val" data-m="tokens">{{ format_compact_number($row['tokens']) }} tok</span>
                        <span class="pm-val" data-m="cost" hidden>{{ format_idr_usd($row['cost']) }}</span>
                        <span class="pm-track">
                            <span class="pm-fill" data-m="tokens" style="width: {{ max(2, round($row['tokens'] / $maxModelTokens * 100)) }}%"></span>
                            <span class="pm-fill cost" data-m="cost" hidden style="width: {{ max(2, round($row['cost'] / $maxModelCost * 100)) }}%"></span>
                        </span>
                        <span class="pm-meta"><b>{{ number_format($row['requests']) }}</b> request <span class="sep">·</span> <b>{{ format_compact_number($row['input_tokens']) }}</b> in <span class="sep">·</span> <b>{{ format_compact_number($row['output_tokens']) }}</b> out
                            @if($row['errors'] > 0) <span class="sep">·</span> <span class="pm-err">{{ $row['errors'] }} error</span>@endif</span>
                    </div>
                @endforeach
            </div>
            <div class="chart-legend" data-legend="tokens"><span><i style="background:#3b82f6"></i>{{ __('dashboard.common.tokens') }}</span></div>
            <div class="chart-legend" data-legend="cost" hidden><span><i style="background:#f59e0b"></i>{{ __('dashboard.common.cost') }}</span></div>
        @else
            <div class="chart-empty">{{ __('dashboard.pages.usage.empty_30') }}</div>
        @endif
    </div>
</div>

<script>
(function () {
    var area = document.getElementById('usage-area-chart');
    var dailyChart = document.querySelector('[data-chart="daily"]');
    var colors = {tokens:'#2563eb', cost:'#d97706', requests:'#7c3aed'};
    var points = area ? JSON.parse(area.getAttribute('data-points') || '[]') : [];

    function compact(value) {
        if (value >= 1000000) return (value / 1000000).toFixed(1).replace('.0', '') + 'M';
        if (value >= 1000) return (value / 1000).toFixed(1).replace('.0', '') + 'K';
        return String(Math.round(value));
    }

    function renderArea(metric) {
        if (!area || !points.length) return;
        var svg = area.querySelector('svg');
        var tooltip = area.querySelector('.area-tooltip');
        var values = points.map(function (p) { return Number(p[metric]) || 0; });
        var max = Math.max.apply(null, values.concat([1]));
        var left = 42, right = 12, top = 15, bottom = 30, width = 640, height = 238;
        var usableW = width - left - right, usableH = height - top - bottom;
        var coords = values.map(function (v, i) {
            return {x:left + (i * usableW / Math.max(1, values.length - 1)), y:top + usableH - (v / max * usableH), value:v, point:points[i]};
        });
        var line = coords.map(function (p, i) { return (i ? 'L' : 'M') + p.x.toFixed(1) + ' ' + p.y.toFixed(1); }).join(' ');
        var fill = line + ' L' + coords[coords.length - 1].x.toFixed(1) + ' ' + (top + usableH) + ' L' + left + ' ' + (top + usableH) + ' Z';
        var color = colors[metric];
        var html = '<defs><linearGradient id="usage-area-gradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + color + '" stop-opacity=".28"/><stop offset="1" stop-color="' + color + '" stop-opacity=".02"/></linearGradient></defs>';
        [0, .25, .5, .75, 1].forEach(function (ratio) {
            var y = top + usableH * ratio;
            html += '<line class="area-grid" x1="' + left + '" y1="' + y + '" x2="' + (width-right) + '" y2="' + y + '"/>';
            html += '<text class="area-axis-label" x="' + (left-7) + '" y="' + (y+3) + '" text-anchor="end">' + compact(max * (1-ratio)) + '</text>';
        });
        coords.forEach(function (p, i) {
            if (i % 2 === 0 || i === coords.length - 1) html += '<text class="area-axis-label" x="' + p.x + '" y="' + (height-7) + '" text-anchor="middle">' + p.point.label + '</text>';
        });
        html += '<path class="area-fill" d="' + fill + '"/><path class="area-line" style="stroke:' + color + '" d="' + line + '"/>';
        coords.forEach(function (p, i) {
            html += '<circle class="area-hit" data-i="' + i + '" cx="' + p.x + '" cy="' + p.y + '" r="13"/><circle class="area-point" style="stroke:' + color + '" cx="' + p.x + '" cy="' + p.y + '" r="3.5"/>';
        });
        svg.innerHTML = html;
        svg.querySelectorAll('.area-hit').forEach(function (hit) {
            hit.addEventListener('mouseenter', function () {
                var p = coords[Number(hit.getAttribute('data-i'))], d = p.point;
                var value = metric === 'cost' ? '$' + Number(p.value).toFixed(6) : compact(p.value) + (metric === 'tokens' ? ' ' + @json(__('dashboard.pages.usage.unit_token')) : ' ' + @json(__('dashboard.pages.usage.unit_request')));
                tooltip.innerHTML = '<strong>' + d.short + ', ' + d.label + '</strong>' + value + '<br><span>' + @json(__('dashboard.pages.usage.request_and_error')).replace(':requests', d.requests).replace(':errors', d.errors) + '</span>';
                tooltip.style.left = (p.x / width * 100) + '%';
                tooltip.style.top = (p.y / height * 100) + '%';
                tooltip.classList.add('show');
            });
            hit.addEventListener('mouseleave', function () { tooltip.classList.remove('show'); });
        });
        var total = values.reduce(function (sum, value) { return sum + value; }, 0);
        var totalEl = dailyChart.querySelector('[data-area-total]');
        totalEl.textContent = metric === 'cost' ? '$' + total.toFixed(6) : compact(total) + (metric === 'tokens' ? ' ' + @json(__('dashboard.pages.usage.unit_token')) : ' ' + @json(__('dashboard.pages.usage.unit_request')));
    }

    document.querySelectorAll('[data-chart]').forEach(function (chart) {
        var tabs = chart.querySelectorAll('.chart-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var metric = tab.getAttribute('data-metric');
                tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
                if (chart === dailyChart) return renderArea(metric);
                chart.querySelectorAll('[data-m]').forEach(function (el) { el.hidden = el.getAttribute('data-m') !== metric; });
                chart.querySelectorAll('[data-legend]').forEach(function (el) { el.hidden = el.getAttribute('data-legend') !== metric; });
            });
        });
    });
    renderArea('tokens');
})();
</script>

<form class="rl-filters" method="get" action="{{ route('usage') }}">
    <div class="rl-field">
        <label for="rl-search">{{ __('dashboard.common.search') }}</label>
        <input id="rl-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Request ID / model" autocomplete="off">
    </div>
    <div class="rl-field">
        <label for="rl-key">{{ __('dashboard.titles.keys') }}</label>
        <select id="rl-key" name="api_key_id">
            <option value="">{{ __('dashboard.pages.usage.all_keys') }}</option>
            @foreach($apiKeys as $key)
                <option value="{{ $key->id }}" @selected(($filters['api_key_id'] ?? '') == $key->id)>{{ $key->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="rl-field">
        <label for="rl-endpoint">{{ __('dashboard.overview.endpoint') }}</label>
        <select id="rl-endpoint" name="endpoint">
            <option value="">{{ __('dashboard.common.all') }}</option>
            @foreach($endpoints as $ep)
                <option value="{{ $ep }}" @selected(($filters['endpoint'] ?? '') === $ep)>{{ $ep }}</option>
            @endforeach
        </select>
    </div>
    <div class="rl-field">
        <label for="rl-status">{{ __('dashboard.common.status') }}</label>
        <select id="rl-status" name="status">
            <option value="">{{ __('dashboard.common.all') }}</option>
            <option value="success" @selected(($filters['status'] ?? '') === 'success')>{{ __('dashboard.pages.usage.success_filter') }}</option>
            <option value="error" @selected(($filters['status'] ?? '') === 'error')>{{ __('dashboard.pages.usage.error_filter') }}</option>
        </select>
    </div>
    <div class="rl-field">
        <label for="rl-from">{{ __('dashboard.common.from') }}</label>
        <input id="rl-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
    </div>
    <div class="rl-field">
        <label for="rl-to">{{ __('dashboard.common.to') }}</label>
        <input id="rl-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
    </div>
    <div class="rl-actions">
        <button>{{ __('dashboard.common.apply') }}</button>
        <a href="{{ route('usage') }}" class="btn secondary">{{ __('dashboard.common.reset') }}</a>
        <a href="{{ route('usage.export', array_filter($filters)) }}" class="btn secondary">{{ __('dashboard.common.export_csv') }}</a>
    </div>
</form>

<div class="section card">
    <h3>{{ __('dashboard.pages.usage.request_log') }}</h3>
    <div class="table-wrap"><table>
        <tr><th>{{ __('dashboard.common.time') }}</th><th>{{ __('dashboard.titles.keys') }}</th><th>{{ __('dashboard.common.model') }}</th><th>{{ __('dashboard.overview.endpoint') }}</th><th>{{ __('dashboard.pages.usage.input_output') }}</th><th>{{ __('dashboard.common.cost') }}</th><th>{{ __('dashboard.pages.usage.cache') }}</th><th>{{ __('dashboard.pages.usage.latency') }}</th><th>{{ __('dashboard.common.status') }}</th><th>{{ __('dashboard.pages.usage.ip') }}</th></tr>
        @forelse($usageLogs as $log)
            @php
                $detail = [
                    'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                    'request_id' => $log->request_id,
                    'key' => $log->apiKey?->name,
                    'prefix' => $log->apiKey?->prefix,
                    'model' => $log->model,
                    'endpoint' => $log->endpoint,
                    'input_tokens' => (int) $log->input_tokens,
                    'output_tokens' => (int) $log->output_tokens,
                    'cost' => format_idr_usd($log->cost),
                    'cache' => $log->cache_read ? 'hit' : ($log->cache_write ? 'write' : null),
                    'latency_ms' => $log->latency_ms,
                    'status_code' => $log->status_code,
                    'source' => $log->usage_source,
                    'ip' => $log->ip_address,
                    'ua' => $log->user_agent,
                ];
                $statusBadge = ($log->status_code >= 500) ? 'red' : (($log->status_code >= 400) ? 'amber' : 'green');
                $cacheBadge = $log->cache_read ? 'green' : ($log->cache_write ? 'amber' : null);
            @endphp
            <tr class="rl-row" data-log='@json($detail)' tabindex="0" role="button" aria-label="{{ __('dashboard.pages.usage.view_detail', ['id' => $log->request_id ?? '']) }}">
                <td title="{{ $log->created_at?->format('Y-m-d H:i:s') }}">{{ $log->created_at?->locale(app()->getLocale())->diffForHumans() }}</td>
                <td><strong>{{ $log->apiKey?->name ?? '—' }}</strong></td>
                <td>{{ $log->model }}</td>
                <td><span class="hint">{{ $log->endpoint }}</span></td>
                <td>{{ number_format($log->input_tokens) }} / {{ number_format($log->output_tokens) }}</td>
                <td>{{ format_idr_usd($log->cost) }}</td>
                <td>@if($cacheBadge)<span class="badge {{ $cacheBadge }}">{{ $log->cache_read ? __('dashboard.pages.usage.cache_hit') : __('dashboard.pages.usage.cache_write') }}</span>@else<span class="muted">—</span>@endif</td>
                <td>{{ $log->latency_ms ? $log->latency_ms.' ms' : '—' }}</td>
                <td><span class="badge {{ $statusBadge }}">{{ $log->status_code }}</span></td>
                <td>{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="rl-empty">
                <h3>{{ __('dashboard.pages.usage.empty') }}</h3>
                <p>{{ __('dashboard.pages.usage.empty_hint') }}</p>
            </td></tr>
        @endforelse
    </table></div>
    <div class="section">{{ $usageLogs->links() }}</div>
</div>

<div class="modal-backdrop" id="log-modal" aria-hidden="true">
    <div class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="log-modal-title">
        <h3 id="log-modal-title">{{ __('dashboard.pages.usage.detail') }}</h3>
        <dl class="dl" id="log-detail-body"></dl>
        <div class="modal-actions">
            <button class="btn secondary" data-close-log-modal>{{ __('dashboard.common.close') }}</button>
        </div>
    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('log-modal');
    var body = document.getElementById('log-detail-body');
    var closeBtn = backdrop.querySelector('[data-close-log-modal]');

    function esc(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function statusClass(code) {
        if (code >= 500) return 'red';
        if (code >= 400) return 'amber';
        return 'green';
    }
    function row(label, value) {
        return '<dt>' + esc(label) + '</dt><dd>' + value + '</dd>';
    }
    var L = {
        waktu: @json(__('dashboard.common.time')),
        request_id: 'Request ID',
        api_key: @json(__('dashboard.titles.keys')),
        model: @json(__('dashboard.common.model')),
        endpoint: @json(__('dashboard.overview.endpoint')),
        tokens_inout: @json(__('dashboard.pages.usage.input_output')),
        biaya: @json(__('dashboard.common.cost')),
        cache: @json(__('dashboard.pages.usage.cache')),
        latency: @json(__('dashboard.pages.usage.latency')),
        status: @json(__('dashboard.common.status')),
        source: 'Source',
        ip: @json(__('dashboard.pages.usage.ip_address')),
        ua: @json(__('dashboard.pages.usage.user_agent')),
        copy: @json(__('dashboard.common.copy')),
        copied: @json(__('dashboard.common.copied')),
        hit: @json(__('dashboard.pages.usage.cache_hit')),
        write: @json(__('dashboard.pages.usage.cache_write'))
    };
    function render(d) {
        var html = '';
        html += row(L.waktu, esc(d.created_at || '—'));
        html += row(L.request_id, '<div class="rid"><span class="mono">' + esc(d.request_id || '—') + '</span>' +
            (d.request_id ? '<button class="btn small secondary" data-copy="' + esc(d.request_id) + '">' + L.copy + '</button>' : '') + '</div>');
        html += row(L.api_key, esc(d.key || '—') + (d.prefix ? ' <span class="hint">(' + esc(d.prefix) + '…)</span>' : ''));
        html += row(L.model, esc(d.model || '—'));
        html += row(L.endpoint, esc(d.endpoint || '—'));
        html += row(L.tokens_inout, esc(d.input_tokens) + ' / ' + esc(d.output_tokens));
        html += row(L.biaya, esc(d.cost || 'Rp 0'));
        html += row(L.cache, d.cache === 'hit' ? '<span class="badge green">' + L.hit + '</span>' : (d.cache === 'write' ? '<span class="badge amber">' + L.write + '</span>' : '—'));
        html += row(L.latency, d.latency_ms != null ? esc(d.latency_ms) + ' ms' : '—');
        html += row(L.status, '<span class="badge ' + statusClass(Number(d.status_code)) + '">' + esc(d.status_code) + '</span>');
        html += row(L.source, esc(d.source || '—'));
        html += row(L.ip, esc(d.ip || '—'));
        html += row(L.ua, esc(d.ua || '—'));
        return html;
    }

    function open(detail) {
        body.innerHTML = render(detail);
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
    }
    function close() {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (e) {
        if (!(e.target instanceof Element)) return;

        var copyBtn = e.target.closest('[data-copy]');
        if (copyBtn) {
            var text = copyBtn.getAttribute('data-copy');
            function flashCopied() {
                var orig = copyBtn.textContent;
                copyBtn.classList.add('copied');
                copyBtn.textContent = L.copied + ' ✓';
                setTimeout(function () { copyBtn.classList.remove('copied'); copyBtn.textContent = orig; }, 1300);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(flashCopied).catch(flashCopied);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (err) {}
                document.body.removeChild(ta);
                flashCopied();
            }
            return;
        }

        var rowEl = e.target.closest('.rl-row');
        if (rowEl) {
            try { open(JSON.parse(rowEl.getAttribute('data-log') || '{}')); } catch (err) {}
        }
    });

    document.querySelectorAll('.rl-row').forEach(function (rowEl) {
        rowEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                try { open(JSON.parse(rowEl.getAttribute('data-log') || '{}')); } catch (err) {}
            }
        });
    });

    closeBtn.addEventListener('click', close);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
@endsection

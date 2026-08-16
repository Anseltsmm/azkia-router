@extends('layouts.app')

@section('content')
<style>
    .lb-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px}
    .lb-sum .card{padding:8px 12px}
    .lb-sum .lbl{font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
    .lb-sum .val{font-size:14px;font-weight:800;letter-spacing:-.02em;margin-top:2px;font-variant-numeric:tabular-nums}
    .lb-sum .sub{font-size:10.5px;color:var(--muted);margin-top:1px}

    .lb-toolbar{display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
    .lb-range{width:auto;margin:0;font-size:13px;flex:0 0 auto}

    .lb-table table{min-width:0}
    .lb-table td .lb-rank{margin:2px 0}
    .lb-rank{position:relative;display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:6px;font-weight:800;font-size:10.5px;font-variant-numeric:tabular-nums;overflow:hidden}
    .lb-rank.gold{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff}
    .lb-rank.silver{background:linear-gradient(135deg,#94a3b8,#64748b);color:#fff}
    .lb-rank.bronze{background:linear-gradient(135deg,#f97316,#b45309);color:#fff}
    .lb-rank.plain{background:var(--soft);color:var(--muted)}
    /* Efek mengkilap — berbeda tiap peringkat top 3 */
    /* 🥇 Emas: kilap atas statis + sapuan cahaya cepat kiri→kanan */
    .lb-rank.gold::before{content:"";position:absolute;inset:0 0 48% 0;background:linear-gradient(180deg,rgba(255,255,255,.55),rgba(255,255,255,0));border-radius:inherit;pointer-events:none}
    .lb-rank.gold::after{content:"";position:absolute;top:-40%;bottom:-40%;left:-80%;width:45%;transform:rotate(25deg);background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.85),rgba(255,255,255,0));animation:lbShineGold 2.2s ease-in-out infinite;pointer-events:none}
    /* 🥈 Perak: kilap atas lebih tegas + sapuan tipis cepat dengan jeda */
    .lb-rank.silver::before{content:"";position:absolute;inset:0 0 42% 0;background:linear-gradient(180deg,rgba(255,255,255,.7),rgba(255,255,255,0));border-radius:inherit;pointer-events:none}
    .lb-rank.silver::after{content:"";position:absolute;top:-45%;bottom:-45%;left:-60%;width:22%;transform:rotate(-18deg);background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.9),rgba(255,255,255,0));animation:lbShineSilver 3s ease-in-out .6s infinite;pointer-events:none}
    /* 🥉 Perunggu: kilap bawah hangat + sapuan dari kanan→kiri dengan jeda panjang */
    .lb-rank.bronze::before{content:"";position:absolute;inset:56% 0 0 0;background:linear-gradient(0deg,rgba(255,255,255,.35),rgba(255,255,255,0));border-radius:inherit;pointer-events:none}
    .lb-rank.bronze::after{content:"";position:absolute;top:-50%;bottom:-50%;right:-80%;width:35%;transform:rotate(-22deg);background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.75),rgba(255,255,255,0));animation:lbShineBronze 3.6s ease-in-out 1.1s infinite;pointer-events:none}
    @keyframes lbShineGold{0%,62%{left:-80%}92%,100%{left:140%}}
    @keyframes lbShineSilver{0%,72%{left:-60%}96%,100%{left:150%}}
    @keyframes lbShineBronze{0%,70%{right:-80%}95%,100%{right:145%}}
    /* Kilap halus kontinu di TEKS nama model — sapuan gradien seamless, tanpa lompatan */
    .lb-model{position:relative;display:inline-block;border-radius:4px}
    .lb-model.gold{background:linear-gradient(90deg,#b45309,#d97706,#fde68a,#d97706,#b45309);background-size:200% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:lbTextShine 3.6s linear infinite}
    .lb-model.silver{background:linear-gradient(90deg,#475569,#64748b,#e2e8f0,#64748b,#475569);background-size:200% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:lbTextShine 3.6s linear infinite}
    .lb-model.bronze{background:linear-gradient(90deg,#9a3412,#c2410c,#fed7aa,#c2410c,#9a3412);background-size:200% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:lbTextShine 3.6s linear infinite}
    @keyframes lbTextShine{0%{background-position:200% 0}100%{background-position:-200% 0}}
    .lb-num{font-variant-numeric:tabular-nums;font-weight:650;white-space:nowrap}
    .lb-num-main{font-weight:800;color:var(--brand)}

    @media (max-width:639.98px){
        .lb-summary{grid-template-columns:1fr;gap:6px}
    }
</style>

<div class="top">
    <div><h2>{{ __('dashboard.pages.leaderboard.heading') }}</h2><p>{{ __('dashboard.pages.leaderboard.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.pages.leaderboard.models', ['count' => $leaderboard->count()]) }}</span>
</div>

<div class="lb-summary">
    <div class="lb-sum"><div class="card"><div class="lbl">{{ __('dashboard.pages.leaderboard.total_tokens') }}</div><div class="val">{{ format_compact_number($totalTokens) }}</div><div class="sub">{{ __('dashboard.pages.leaderboard.all_usage') }}</div></div></div>
    <div class="lb-sum"><div class="card"><div class="lbl">{{ __('dashboard.pages.leaderboard.total_requests') }}</div><div class="val">{{ number_format($totalRequests) }}</div><div class="sub">{{ __('dashboard.pages.leaderboard.selected_range') }}</div></div></div>
    <div class="lb-sum"><div class="card"><div class="lbl">{{ __('dashboard.pages.leaderboard.total_cost') }}</div><div class="val">{{ format_idr_usd($totalCost) }}</div><div class="sub">{{ __('dashboard.pages.leaderboard.all_usage') }}</div></div></div>
</div>

<form method="get" action="{{ route('leaderboard') }}" class="lb-toolbar" role="search">
    <label class="muted" for="lb-range" style="font-size:13px">{{ __('dashboard.pages.leaderboard.range') }}</label>
    <select id="lb-range" name="range" class="lb-range" onchange="this.form.submit()">
        <option value="all" @selected($range === 'all')>{{ __('dashboard.pages.leaderboard.all_time') }}</option>
        <option value="30d" @selected($range === '30d')>{{ __('dashboard.pages.leaderboard.last_30') }}</option>
        <option value="7d" @selected($range === '7d')>{{ __('dashboard.pages.leaderboard.last_7') }}</option>
        <option value="today" @selected($range === 'today')>{{ __('dashboard.pages.leaderboard.today') }}</option>
    </select>
    <span class="muted" style="font-size:12px">{{ __('dashboard.pages.leaderboard.calculation') }}</span>
</form>

<div class="table-wrap lb-table"><table>
    <tr><th style="width:56px">{{ __('dashboard.pages.leaderboard.rank_col') }}</th><th>{{ __('dashboard.pages.leaderboard.model_col') }}</th><th>{{ __('dashboard.pages.leaderboard.requests_col') }}</th><th>{{ __('dashboard.pages.leaderboard.tokens_col') }}</th></tr>
    @forelse($leaderboard as $index => $row)
        @php
            $rank = $index + 1;
            $rankClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : 'plain'));
        @endphp
        <tr title="{{ $row->last_used ? __('dashboard.pages.leaderboard.last_used', ['time' => $row->last_used->locale(app()->getLocale())->diffForHumans()]) : __('dashboard.pages.leaderboard.never_used') }}">
            <td><span class="lb-rank {{ $rankClass }}" title="{{ $rank <= 3 ? [__('dashboard.pages.leaderboard.champion_1'), __('dashboard.pages.leaderboard.champion_2'), __('dashboard.pages.leaderboard.champion_3')][$rank-1] : __('dashboard.pages.leaderboard.rank', ['rank' => $rank]) }}">{{ $rank }}</span></td>
            <td><strong class="lb-model {{ $rankClass }}">{{ $row->model }}</strong></td>
            <td class="lb-num">{{ number_format($row->requests) }}</td>
            <td class="lb-num lb-num-main" title="{{ __('dashboard.pages.leaderboard.tokens_total', ['count' => number_format($row->tokens)]) }}">{{ format_compact_number($row->tokens) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" style="text-align:center;color:var(--muted)">{{ __('dashboard.pages.leaderboard.empty') }}</td></tr>
    @endforelse
</table></div>
@endsection

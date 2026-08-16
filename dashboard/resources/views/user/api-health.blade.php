@extends('layouts.app')

@section('content')
@php
    $status = $health['status'] ?? null;
    $badgeClass = match ($status) { 'ok' => 'green', 'degraded' => 'amber', default => 'red' };
    $badgeText = match ($status) { 'ok' => __('dashboard.status.operational'), 'degraded' => __('dashboard.status.degraded'), default => __('dashboard.status.offline') };
    $uptime = $health['uptime_seconds'] ?? null;
    if ($uptime !== null) {
        $d = (int) floor($uptime / 86400); $h = (int) floor(($uptime % 86400) / 3600); $m = (int) floor(($uptime % 3600) / 60); $s = (int) ($uptime % 60);
        $uptimeText = ($d ? $d.'d ' : '').($h ? $h.'h ' : '').($m ? $m.'m ' : '').$s.'s';
    } else { $uptimeText = '—'; }
    $hiddenFields = ['database', 'redis', 'active_models'];
    $details = array_filter($health ?? [], fn ($key) => ! in_array($key, $hiddenFields, true), ARRAY_FILTER_USE_KEY);
@endphp
<div class="top"><div><h2>{{ __('dashboard.pages.api_health.heading') }}</h2><p>{{ __('dashboard.pages.api_health.subtitle') }}</p></div><span class="pill">{{ __('dashboard.pages.api_health.live') }}</span></div>
@if($error)
    <div class="section card" style="border-color:var(--red-line);background:var(--red-soft)"><span class="badge red">{{ __('dashboard.status.offline') }}</span><h3 style="margin:10px 0 4px">{{ __('dashboard.pages.api_health.unreachable') }}</h3><p style="margin:0;color:var(--red-ink)">{{ $error }}</p></div>
@elseif($health)
    <div class="hero" style="background:linear-gradient(135deg,#f0fdf4 0%,#fafbff 55%,#eef8ff 100%);border-color:var(--green-line)"><div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><span class="badge {{ $badgeClass }}" style="font-size:13px;padding:5px 13px">{{ $badgeText }}</span><h2 style="margin:0;font-size:22px">{{ $health['service'] ?? 'azkia-gateway' }} · v{{ $health['version'] ?? '—' }}</h2></div><p style="margin:8px 0 0">{{ __('dashboard.pages.api_health.checked', ['uptime' => $uptimeText, 'latency' => $latencyMs ?? '—', 'date' => now()->locale(app()->getLocale())->translatedFormat('d M Y H:i:s')]) }}</p></div>
    <div class="section card"><h3>{{ __('dashboard.pages.api_health.details') }}</h3><div class="table-wrap"><table><tr><th>{{ __('dashboard.common.field') }}</th><th>{{ __('dashboard.common.value') }}</th></tr>@forelse($details as $key => $value)<tr><td><strong>{{ $key }}</strong></td><td>{{ is_array($value) ? json_encode($value) : $value }}</td></tr>@empty<tr><td colspan="2" style="text-align:center;color:var(--muted)">{{ __('dashboard.pages.api_health.empty_details') }}</td></tr>@endforelse</table></div></div>
@else
    <div class="section card"><p class="muted">{{ __('dashboard.pages.api_health.empty') }}</p></div>
@endif
@endsection

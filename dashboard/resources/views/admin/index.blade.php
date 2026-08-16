@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>Admin Dashboard</h2><p>Operational control untuk Azkia Router.</p></div>
    <span class="pill">admin.azkia.cloud</span>
</div>

<div class="grid">
    <div class="card"><span class="badge">Users</span><div class="metric">{{ $totalUsers }}</div><p class="muted">{{ $activeUsers }} aktif</p></div>
    <div class="card"><span class="badge green">Revenue</span><div class="metric">{{ format_idr_usd($totalRevenue) }}</div><p class="muted">Manual topup</p></div>
    <div class="card"><span class="badge">Usage Cost</span><div class="metric">{{ format_idr_usd($totalUsageCost) }}</div><p class="muted">Tracked cost</p></div>
    <div class="card"><span class="badge">Requests</span><div class="metric">{{ number_format($totalRequests) }}</div><p class="muted">Gateway usage</p></div>
</div>

<div class="section grid3">
    <div class="card">
        <h3>Providers</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13px">{{ $totalProviders }} terdaftar — kelola upstream API, toggle aktif/nonaktif.</p>
        <a class="btn" href="{{ route('admin.providers') }}" style="text-decoration:none">Kelola Provider</a>
    </div>
    <div class="card">
        <h3>Models</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13px">{{ $activeModels }} model aktif — daftarkan alias publik & kemampuan multi-modal.</p>
        <a class="btn" href="{{ route('admin.models') }}" style="text-decoration:none">Kelola Models</a>
    </div>
    <div class="card">
        <h3>Pricing</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13px">{{ $pricingCount }} rule aktif — set harga input/output & cache per 1M token.</p>
        <a class="btn" href="{{ route('admin.pricing') }}" style="text-decoration:none">Kelola Pricing</a>
    </div>
    <div class="card">
        <h3>API Keys</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13px">{{ $apiKeyCount }} key aktif — atur rate limit & kuota bulanan per key.</p>
        <a class="btn" href="{{ route('admin.keys') }}" style="text-decoration:none">Kelola API Keys</a>
    </div>
    <div class="card">
        <h3>Users</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13px">{{ $totalUsers }} user terdaftar — topup saldo & kontrol status akun.</p>
        <a class="btn" href="{{ route('admin.users') }}" style="text-decoration:none">Kelola Users</a>
    </div>
    <div class="card">
        <h3>API Health</h3>
        <p class="muted" style="margin:0 0 12px;font-size:13px">Status layanan gateway, uptime & latensi realtime.</p>
        <a class="btn" href="{{ route('api-health') }}" style="text-decoration:none">Lihat Status</a>
    </div>
</div>
@endsection

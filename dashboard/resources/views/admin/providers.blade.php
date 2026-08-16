@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>Providers</h2><p>Daftarkan upstream API (OpenAI-compatible) yang dipakai gateway.</p></div>
    <span class="pill">{{ $providers->count() }} providers</span>
</div>

@include('admin._alerts')

<div class="section grid2">
    <div class="card">
        <h3>Add Provider</h3>
        <form method="post" action="{{ route('admin.providers.store') }}">@csrf
            <label class="muted">Name</label><input name="name" placeholder="OpenAI Compatible" required>
            <label class="muted">Base URL</label><input name="base_url" placeholder="https://provider.com/v1" required>
            <label class="muted">API Key</label><input name="api_key" placeholder="Optional">
            <button>Save Provider</button>
        </form>
    </div>
    <div class="card">
        <h3>Catatan</h3>
        <p class="muted" style="font-size:13.5px;margin:0 0 10px">Provider tanpa API key akan memakai key default dari env gateway. Model yang memakai provider ini dirutekan ke <code style="background:var(--soft);padding:2px 6px;border-radius:6px;font-size:12.5px">base_url</code> tersebut.</p>
        <p class="muted" style="font-size:13.5px;margin:0">Toggle <strong>Disable</strong> akan menonaktifkan provider. <strong>Catatan:</strong> model yang memakai provider nonaktif tetap bisa dirutekan ke key default env — pastikan mengatur model dengan benar.</p>
    </div>
</div>

<div class="section card">
    <h3>Daftar Provider</h3>
    <div class="table-wrap"><table><tr><th>Name</th><th>Base URL</th><th>Models</th><th>Status</th><th>Action</th></tr>
        @forelse($providers as $provider)
        <tr>
            <td><strong>{{ $provider->name }}</strong></td>
            <td style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px">{{ $provider->base_url }}</td>
            <td>{{ $provider->ai_models_count }}</td>
            <td><span class="badge {{ $provider->is_active ? 'green' : 'red' }}">{{ $provider->is_active ? 'active' : 'off' }}</span></td>
            <td><form method="post" action="{{ route('admin.providers.toggle', $provider) }}">@csrf @method('patch')<button class="secondary">{{ $provider->is_active ? 'Disable' : 'Enable' }}</button></form></td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;color:var(--muted)">Belum ada provider — tambahkan di atas.</td></tr>
        @endforelse
    </table></div>
</div>
@endsection

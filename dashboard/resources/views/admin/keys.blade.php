@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>API Keys</h2><p>Rate limit &amp; kuota diatur di sini — user tidak bisa mengubahnya sendiri.</p></div>
    <span class="pill">{{ $apiKeys->count() }} keys</span>
</div>

@include('admin._alerts')

<div class="section card">
    <div class="table-wrap"><table>
        <tr><th>User</th><th>Name</th><th>Prefix</th><th>Limits (rate/min · kuota token)</th><th>Status</th><th>Actions</th></tr>
        @forelse($apiKeys as $key)
        <tr>
            <td><strong>{{ $key->user?->name ?? '—' }}</strong></td>
            <td>{{ $key->name }}</td>
            <td><span style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px">{{ $key->prefix }}</span></td>
            <td>
                <form class="compact" method="post" action="{{ route('admin.api-keys.update', $key) }}">@csrf @method('patch')
                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <input name="rate_limit_per_minute" type="number" min="1" value="{{ $key->rate_limit_per_minute }}" title="Rate limit per menit" style="width:86px" required>
                        <input name="monthly_quota_tokens" type="number" min="1" value="{{ $key->monthly_quota_tokens ?? '' }}" placeholder="Kuota token" title="Kuota bulanan (token)" style="width:110px">
                        <input name="expires_at" type="date" value="{{ $key->expires_at?->format('Y-m-d') }}" title="Kedaluwarsa (kosongkan = tanpa batas)" style="width:145px">
                        <button class="secondary">Simpan</button>
                    </div>
                </form>
            </td>
            <td><span class="badge {{ $key->is_active ? 'green' : 'red' }}">{{ $key->is_active ? 'active' : 'off' }}</span></td>
            <td>
                <form method="post" action="{{ route('admin.api-keys.toggle', $key) }}">@csrf @method('patch')<button class="secondary">{{ $key->is_active ? 'Disable' : 'Enable' }}</button></form>
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--muted)">Belum ada API key.</td></tr>
        @endforelse
    </table></div>
</div>
@endsection

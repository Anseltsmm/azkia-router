@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>Request Ditolak</h2><p>Audit trail request API yang ditolak sebelum billing (auth, rate limit, model, capability) — dicatat otomatis oleh gateway.</p></div>
    <span class="pill">{{ number_format($rows->total()) }} rejection</span>
</div>

@include('admin._alerts')

@if($summary->isNotEmpty())
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:4px">
    @foreach($summary as $s)
    <div class="card" style="padding:14px 16px">
        <span class="badge {{ $s->status_code >= 500 ? 'red' : ($s->status_code >= 400 ? 'amber' : 'green') }}">HTTP {{ $s->status_code }}</span>
        <div class="metric small">{{ number_format($s->total) }}</div>
        <p class="muted" style="margin:2px 0 0;font-size:12px">{{ match((int) $s->status_code) {
            400 => 'kapabilitas model',
            401 => 'auth gagal',
            402 => 'saldo / pricing',
            403 => 'user terblokir',
            404 => 'model tidak ada',
            429 => 'rate limit',
            default => 'lainnya',
        } }}</p>
    </div>
    @endforeach
</div>
@endif

<div class="section card">
    <form method="get" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <label style="font-size:12px;color:var(--muted)">Cari (model / endpoint / alasan)</label>
            <input name="search" value="{{ $search }}" placeholder="mis. deepseek, /v1/chat/completions..." style="margin-bottom:0">
        </div>
        <div style="min-width:140px">
            <label style="font-size:12px;color:var(--muted)">Status</label>
            <select name="status" style="margin-bottom:0">
                <option value="">Semua</option>
                @foreach([400, 401, 402, 403, 404, 429] as $code)
                <option value="{{ $code }}" @selected((string) $status === (string) $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
        <button class="secondary">Filter</button>
        @if($status !== null && $status !== '' || $search)
        <a class="btn secondary" href="{{ route('admin.rejections') }}">Reset</a>
        @endif
    </form>
</div>

<div class="section table-wrap"><table>
    <tr><th>Waktu</th><th>User</th><th>Endpoint</th><th>Model</th><th>Status</th><th>Alasan</th><th>IP</th></tr>
    @forelse($rows as $row)
    <tr>
        <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M H:i:s') }}</td>
        <td>{{ $row->user_email ?: '—' }}</td>
        <td><span style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px">{{ $row->endpoint }}</span></td>
        <td>{{ $row->model ?: '—' }}</td>
        <td><span class="badge {{ $row->status_code >= 500 ? 'red' : ($row->status_code >= 400 ? 'amber' : 'green') }}">{{ $row->status_code }}</span></td>
        <td style="max-width:340px"><span style="font-size:12px;color:var(--muted)">{{ \Illuminate\Support\Str::limit($row->reason, 120) }}</span></td>
        <td><span style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px">{{ $row->ip_address ?: '—' }}</span></td>
    </tr>
    @empty
    <tr><td colspan="7" style="text-align:center;color:var(--muted)">Belum ada request yang ditolak tercatat.</td></tr>
    @endforelse
</table></div>

{{ $rows->links() }}
@endsection

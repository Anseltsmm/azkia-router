@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn secondary" href="{{ route('admin.users') }}" style="text-decoration:none">← Users</a>
        <a class="btn secondary" href="{{ route('admin.users.edit', $user) }}" style="text-decoration:none">Edit</a>
    </div>
</div>

@include('admin._alerts')

<div class="grid">
    <div class="card"><span class="badge {{ $user->status === 'active' ? 'green' : 'red' }}">{{ $user->status }}</span><div class="metric" style="font-size:16px;margin-top:8px">@if($user->is_admin)<span class="badge">admin</span>@endif</div><p class="muted">Terdaftar {{ $user->created_at?->format('d M Y') }}</p></div>
    <div class="card"><span class="badge">Balance</span><div class="metric">{{ format_idr_usd($user->balance) }}</div><p class="muted">Saldo aktif</p></div>
    <div class="card"><span class="badge">Requests</span><div class="metric">{{ number_format((int) ($stats->requests ?? 0)) }}</div><p class="muted">{{ (int) ($stats->errors ?? 0) }} errors</p></div>
    <div class="card"><span class="badge">Cost</span><div class="metric">{{ format_usd($stats->cost ?? 0) }}</div><p class="muted">{{ format_compact_number((int) ($stats->tokens ?? 0)) }} token · {{ $stats->last_used?->diffForHumans() ?? 'belum pernah' }}</p></div>
</div>

<div class="section grid2">
    <div class="card">
        <h3>Topup & Status</h3>
        <form class="compact" method="post" action="{{ route('admin.users.topup', $user) }}">@csrf
            <div class="form-row"><input name="amount" type="number" min="1" step="1" placeholder="Jumlah (IDR)" title="Nominal Rupiah — dikonversi ke USD dengan kurs realtime" required><input name="notes" placeholder="Notes"></div>
            <button>Topup</button>
        </form>
        <form class="compact" method="post" action="{{ route('admin.users.status', $user) }}" style="margin-top:10px">@csrf @method('patch')
            <select name="status"><option value="active" @selected($user->status === 'active')>active</option><option value="suspended" @selected($user->status === 'suspended')>suspended</option></select>
            <button class="secondary">Update Status</button>
        </form>
        <form method="post" action="{{ route('admin.users.destroy', $user) }}" style="margin-top:14px" onsubmit="return confirm('Hapus user {{ $user->name }}? Semua API key & transaksinya ikut terhapus.')">@csrf @method('delete')<button class="danger">Hapus User</button></form>
    </div>
    <div class="card">
        <h3>Kirim Pesan Inbox</h3>
        <form class="compact" method="post" action="{{ route('admin.users.messages.store', $user) }}">@csrf
            <label class="muted">Subjek</label>
            <input name="subject" value="{{ old('subject') }}" maxlength="255" placeholder="Contoh: Informasi akun" required>
            <label class="muted">Pesan</label>
            <textarea name="body" maxlength="5000" rows="5" placeholder="Tulis pesan untuk user..." required style="width:100%;resize:vertical;border:1px solid var(--line);background:var(--input);color:var(--ink);border-radius:var(--r-input);padding:10px 12px;margin:4px 0 10px;font:inherit">{{ old('body') }}</textarea>
            <button>Kirim Pesan</button>
        </form>
    </div>
    <div class="card">
        <h3>API Keys ({{ $apiKeys->count() }})</h3>
        @if($apiKeys->isNotEmpty())
        <div class="table-wrap"><table>
            <tr><th>Name</th><th>Prefix</th><th>Rate/min</th><th>Kuota</th><th>Status</th></tr>
            @foreach($apiKeys as $key)
            <tr>
                <td>{{ $key->name }}</td>
                <td><span style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px">{{ $key->prefix }}</span></td>
                <td>{{ $key->rate_limit_per_minute }}</td>
                <td>{{ $key->monthly_quota_tokens ? format_compact_number($key->monthly_quota_tokens) : '∞' }}</td>
                <td><span class="badge {{ $key->is_active ? 'green' : 'red' }}">{{ $key->is_active ? 'active' : 'off' }}</span></td>
            </tr>
            @endforeach
        </table></div>
        @else
        <p class="muted" style="margin:0">User belum punya API key.</p>
        @endif
    </div>
</div>

<div class="section card">
    <h3>Transaksi Terbaru</h3>
    <div class="table-wrap"><table><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Status</th><th>Notes</th></tr>
        @forelse($transactions as $t)
        <tr>
            <td>{{ $t->created_at?->format('d M Y H:i') }}</td>
            <td>{{ $t->type }}</td>
            <td>{{ format_usd($t->amount) }}</td>
            <td>{{ format_usd($t->balance_after) }}</td>
            <td><span class="badge {{ $t->status === 'completed' ? 'green' : 'amber' }}">{{ $t->status }}</span></td>
            <td class="muted" style="font-size:12.5px">{{ $t->notes ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--muted)">Belum ada transaksi.</td></tr>
        @endforelse
    </table></div>
</div>

<div class="section card">
    <h3>Request Terbaru</h3>
    <div class="table-wrap"><table><tr><th>Time</th><th>Model</th><th>Endpoint</th><th>Tokens</th><th>Cost</th><th>Status</th></tr>
        @forelse($recentUsage as $log)
        <tr>
            <td>{{ $log->created_at?->format('d M H:i:s') }}</td>
            <td style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px">{{ $log->model }}</td>
            <td>{{ $log->endpoint }}</td>
            <td>{{ number_format($log->input_tokens + $log->output_tokens) }}</td>
            <td>{{ format_usd($log->cost) }}</td>
            <td><span class="badge {{ $log->status_code < 400 ? 'green' : 'red' }}">{{ $log->status_code }}</span></td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;color:var(--muted)">Belum ada request.</td></tr>
        @endforelse
    </table></div>
</div>
@endsection

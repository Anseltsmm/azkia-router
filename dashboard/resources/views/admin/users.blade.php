@extends('layouts.app')

@section('content')
<div class="top">
    <div><h2>Users</h2><p>Kelola akun user: topup saldo, status, edit, dan detail pemakaian.</p></div>
    <span class="pill">{{ $users->total() }} users</span>
</div>

@include('admin._alerts')

<form method="get" action="{{ route('admin.users') }}" style="display:flex;gap:8px;margin-bottom:14px;max-width:420px">
    <input type="search" name="search" value="{{ $search }}" placeholder="Cari nama atau email..." aria-label="Cari user">
    <button class="secondary" type="submit">Cari</button>
    @if($search)<a class="btn secondary" href="{{ route('admin.users') }}" style="text-decoration:none">Reset</a>@endif
</form>

<div class="section card">
    <div class="table-wrap"><table>
        <tr><th>User</th><th>Balance</th><th>Requests</th><th>Tokens</th><th>Cost</th><th>Keys Aktif</th><th>Status</th><th>Terakhir dipakai</th><th>Actions</th></tr>
        @forelse($users as $user)
            @php($s = $stats->get($user->id))
        <tr>
            <td><strong>{{ $user->name }}</strong><br><span class="muted" style="font-size:12px">{{ $user->email }}</span>@if($user->is_admin) <span class="badge" style="margin-left:3px">admin</span>@endif</td>
            <td>{{ format_idr_usd($user->balance) }}</td>
            <td>{{ number_format((int) ($s->requests ?? 0)) }}</td>
            <td>{{ format_compact_number((int) ($s->tokens ?? 0)) }}</td>
            <td>{{ $s ? format_usd($s->cost) : '—' }}</td>
            <td>{{ $user->api_keys_count }}</td>
            <td><span class="badge {{ $user->status === 'active' ? 'green' : 'red' }}">{{ $user->status }}</span></td>
            <td>{{ $s?->last_used?->diffForHumans() ?? '—' }}</td>
            <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                    <a class="btn secondary" href="{{ route('admin.users.show', $user) }}" style="text-decoration:none">Detail</a>
                    <a class="btn secondary" href="{{ route('admin.users.edit', $user) }}" style="text-decoration:none">Edit</a>
                    <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user {{ $user->name }}? Semua API key & transaksinya ikut terhapus.')">@csrf @method('delete')<button class="danger">Hapus</button></form>
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;color:var(--muted)">{{ $search ? 'Tidak ada user yang cocok.' : 'Belum ada user.' }}</td></tr>
        @endforelse
    </table></div>
    <div class="pagination" style="margin-top:14px">
        <span class="pg-info">Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} user</span>
        <span class="pg-links">
            @if($users->onFirstPage())<span class="pg-btn disabled">‹</span>@else<a class="pg-btn" href="{{ $users->previousPageUrl() }}">‹</a>@endif
            @foreach($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                <a class="pg-btn {{ $page === $users->currentPage() ? 'current' : '' }}" href="{{ $url }}">{{ $page }}</a>
            @endforeach
            @if($users->hasMorePages())<a class="pg-btn" href="{{ $users->nextPageUrl() }}">›</a>@else<span class="pg-btn disabled">›</span>@endif
        </span>
    </div>
</div>
@endsection

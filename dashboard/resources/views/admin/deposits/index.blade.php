@extends('layouts.app')

@section('content')
<div class="top"><div><h2>Kelola Deposit</h2><p>Pantau, rekonsiliasi, ekspor, dan kredit saldo dengan audit.</p></div><a class="btn secondary" href="{{ route('admin.deposits.export', request()->query()) }}">Export CSV</a></div>
@include('admin._alerts')
<div class="grid">
    <div class="card"><span class="muted">Total Order</span><div class="metric">{{ number_format($stats['total']) }}</div></div>
    <div class="card"><span class="muted">Pending</span><div class="metric">{{ number_format($stats['pending']) }}</div></div>
    <div class="card"><span class="muted">Paid / Credited</span><div class="metric">{{ $stats['paid'] }} / {{ $stats['credited'] }}</div></div>
    <div class="card"><span class="muted">Total Kredit</span><div class="metric small">${{ number_format($stats['credit'], 6) }}</div></div>
</div>
<div class="card section compact">
<form method="get"><div class="grid3">
<div><label class="muted">Cari user / referensi</label><input name="search" value="{{ request('search') }}" placeholder="Nama, email, merchant/tripay ref"></div>
<div><label class="muted">Status</label><select name="status"><option value="">Semua</option>@foreach(['UNPAID','PENDING','PAID','FAILED','EXPIRED','REFUND'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
<div><label class="muted">Metode</label><input name="method" value="{{ request('method') }}" placeholder="BRIVA"></div>
<div><label class="muted">Tanggal mulai</label><input type="date" name="date_from" value="{{ request('date_from') }}"></div>
<div><label class="muted">Tanggal akhir</label><input type="date" name="date_to" value="{{ request('date_to') }}"></div>
<div><label class="muted">Credited</label><select name="credited"><option value="">Semua</option><option value="yes" @selected(request('credited') === 'yes')>Ya</option><option value="no" @selected(request('credited') === 'no')>Belum</option></select></div>
<div><label class="muted">Urutkan</label><select name="sort">@foreach(['created_at','credit_usd','amount_idr','status','credited_at'] as $sort)<option value="{{ $sort }}" @selected(request('sort','created_at') === $sort)>{{ $sort }}</option>@endforeach</select></div>
<div><label class="muted">Arah</label><select name="direction"><option value="desc">Terbaru/besar</option><option value="asc" @selected(request('direction') === 'asc')>Terlama/kecil</option></select></div>
</div><button>Terapkan Filter</button> <a class="btn secondary" href="{{ route('admin.deposits.index') }}">Reset</a></form>
</div>
<div class="section table-wrap"><table><thead><tr><th>Referensi</th><th>User</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a href="{{ route('admin.deposits.show', $order) }}"><strong>{{ $order->merchant_ref }}</strong></a><br><span class="muted">{{ $order->tripay_reference ?: '—' }}</span></td><td>{{ $order->user->name }}<br><span class="muted">{{ $order->user->email }}</span></td><td>{{ $order->payment_method }}</td><td>Rp {{ number_format($order->amount_idr) }}<br>${{ $order->credit_usd }}</td><td><span class="badge {{ $order->status === 'PAID' ? 'green' : ($order->status === 'UNPAID' ? 'amber' : 'red') }}">{{ $order->status }}</span><br><span class="muted">{{ $order->credited_at ? 'credited' : 'not credited' }}</span></td><td>{{ $order->created_at->format('d M Y H:i') }}</td><td><form method="post" action="{{ route('admin.deposits.reconcile', $order) }}">@csrf<button class="secondary">Reconcile</button></form></td></tr>@empty<tr><td colspan="7" class="muted">Tidak ada deposit.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}
<div class="grid2 section">
<div class="card"><h3>Reconcile Filtered Batch</h3><form method="post" action="{{ route('admin.deposits.reconcile-batch') }}">@csrf @foreach(request()->only(['search','status','method','date_from','date_to','credited']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<label class="muted">Maksimum pending order</label><input type="number" name="limit" min="1" max="100" value="100"><button>Jalankan Batch</button></form></div>
<div class="card"><h3>Manual Credit</h3><form method="post" action="{{ route('admin.deposits.manual-credit') }}">@csrf<label class="muted">Target user</label><select name="user_id" required><option value="">Pilih user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach</select><div class="form-row"><div><label class="muted">USD</label><input name="amount" inputmode="decimal" placeholder="10.000000" required></div><div><label class="muted">UUID idempotency</label><input name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}" required></div></div><label class="muted">Alasan</label><input name="reason" minlength="10" required><label class="muted">Password admin saat ini</label><input type="password" name="current_password" autocomplete="current-password" required><button>Kredit Saldo</button></form></div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="top"><div><h2>Audit Log</h2><p>Jejak lengkap setiap aksi finansial & pengaturan — siapa, kapan, nilai sebelum/sesudah.</p></div></div>
@include('admin._alerts')
<div class="grid">
    <div class="card"><span class="muted">Total Audit Event</span><div class="metric">{{ number_format($stats['total']) }}</div></div>
    <div class="card"><span class="muted">Bonus Top Up</span><div class="metric">{{ number_format($stats['topupBonus']) }}</div></div>
    <div class="card"><span class="muted">Reward Referral</span><div class="metric">{{ number_format($stats['referralReward']) }}</div></div>
    <div class="card"><span class="muted">Perubahan Pengaturan Event</span><div class="metric">{{ number_format($stats['settings']) }}</div></div>
</div>
<div class="card section compact">
<form method="get"><div class="grid3">
<div><label class="muted">Action</label><select name="action"><option value="">Semua</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select></div>
<div><label class="muted">Cari actor / target / action</label><input name="search" value="{{ request('search') }}" placeholder="Nama, email, atau action"></div>
<div><label class="muted">Tanggal mulai</label><input type="date" name="date_from" value="{{ request('date_from') }}"></div>
<div><label class="muted">Tanggal akhir</label><input type="date" name="date_to" value="{{ request('date_to') }}"></div>
</div><button>Terapkan Filter</button> <a class="btn secondary" href="{{ route('admin.audit-log') }}">Reset</a></form>
</div>
<div class="section table-wrap"><table><thead><tr><th>Waktu</th><th>Action</th><th>Actor</th><th>Target</th><th>Amount</th><th>Saldo Sebelum → Sesudah</th><th>Detail</th></tr></thead><tbody>
@forelse($events as $event)<tr>
<td>{{ $event->created_at->format('d M Y H:i:s') }}</td>
<td><code>{{ $event->action }}</code></td>
<td>{{ $event->actor?->email ?: ($event->actor_id ? 'user#'.$event->actor_id : 'system') }}</td>
<td>{{ $event->targetUser?->email ?: ($event->target_user_id ? 'user#'.$event->target_user_id : '—') }}</td>
<td>{{ $event->amount !== null ? '$'.number_format((float) $event->amount, 6) : '—' }}</td>
<td>{{ $event->balance_before !== null ? '$'.number_format((float) $event->balance_before, 6).' → $'.number_format((float) $event->balance_after, 6) : '—' }}</td>
<td>@if($event->paymentOrder)<a href="{{ route('admin.deposits.show', $event->paymentOrder) }}">deposit #{{ $event->payment_order_id }}</a><br>@endif<code class="muted">{{ json_encode($event->metadata) }}</code></td>
</tr>@empty<tr><td colspan="7" class="muted">Belum ada audit event.</td></tr>@endforelse
</tbody></table></div>{{ $events->links() }}
@endsection

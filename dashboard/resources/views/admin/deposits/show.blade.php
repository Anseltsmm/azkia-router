@extends('layouts.app')

@section('content')
<div class="top"><div><h2>Detail Deposit</h2><p>{{ $order->merchant_ref }}</p></div><a class="btn secondary" href="{{ route('admin.deposits.index') }}">Kembali</a></div>
@include('admin._alerts')
<div class="grid3">
<div class="card"><h3>Order</h3><p>Status: <span class="badge">{{ $order->status }}</span></p><p>Tripay: {{ $order->tripay_reference ?: '—' }}</p><p>Metode: {{ $order->payment_method }}</p><p>Nominal: Rp {{ number_format($order->amount_idr) }} / ${{ $order->credit_usd }}</p><p>Credited: {{ $order->credited_at ?: 'Belum' }}</p></div>
<div class="card"><h3>User</h3><p><strong>{{ $order->user->name }}</strong></p><p>{{ $order->user->email }}</p><p>Saldo: ${{ $order->user->balance }}</p><a class="btn secondary" href="{{ route('admin.users.show', $order->user) }}">Lihat User</a></div>
<div class="card"><h3>Ledger</h3>@if($order->transaction)<p>Tipe: {{ $order->transaction->type }}</p><p>Amount: ${{ $order->transaction->amount }}</p><p>Before: ${{ $order->transaction->balance_before }}</p><p>After: ${{ $order->transaction->balance_after }}</p><p>Ref: {{ $order->transaction->reference }}</p>@else<p class="muted">Belum ada transaksi ledger.</p>@endif</div>
</div>
<div class="card section"><h3>Tripay Payload Tersanitasi</h3><pre class="key">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
<div class="section table-wrap"><table><thead><tr><th>Waktu</th><th>Action</th><th>Actor</th><th>Metadata</th></tr></thead><tbody>@forelse($audit as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->action }}</td><td>{{ $event->actor?->email ?: 'system' }}</td><td><code>{{ json_encode($event->metadata) }}</code></td></tr>@empty<tr><td colspan="4" class="muted">Belum ada audit event.</td></tr>@endforelse</tbody></table></div>
@endsection

@extends('layouts.app')

@section('content')
<style>
    .payment-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:14px;align-items:start}
    .pay-code{font-family:ui-monospace,monospace;font-size:21px;font-weight:800;letter-spacing:.03em;background:var(--soft);border:1px solid var(--line);border-radius:10px;padding:13px;text-align:center;word-break:break-all}
    .qr{display:block;width:min(240px,100%);margin:14px auto;border-radius:12px;background:#fff;padding:8px}
    .pay-row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid var(--line);font-size:13px}.pay-row:last-child{border:0}.pay-row span{color:var(--muted)}
    @media(max-width:800px){.payment-grid{grid-template-columns:minmax(0,1fr)}}
</style>
@php($paymentStatus = __('dashboard.status.'.strtolower($payment->status)))
<div class="top"><div><h2>{{ __('dashboard.pages.payment.heading') }}</h2><p>{{ $payment->merchant_ref }}</p></div><span class="badge {{ $payment->status === 'PAID' ? 'green' : ($payment->status === 'UNPAID' ? 'amber' : 'red') }}">{{ $paymentStatus }}</span></div>
<div class="payment-grid">
    <div class="card">
        <h3>{{ $payment->payment_name }}</h3>
        @if($payment->pay_code)<p class="muted">{{ __('dashboard.pages.payment.pay_code') }}</p><div class="pay-code">{{ $payment->pay_code }}</div>@endif
        @if($payment->qr_url)<img class="qr" src="{{ $payment->qr_url }}" alt="{{ __('dashboard.pages.payment.qr_alt') }}">@endif
        @if($payment->pay_url)<a class="btn" href="{{ $payment->pay_url }}" target="_blank" rel="noopener" style="margin-top:14px">{{ __('dashboard.pages.payment.open_app') }}</a>@endif
        @if($payment->checkout_url && $payment->status === 'UNPAID')<a class="btn" href="{{ $payment->checkout_url }}" target="_blank" rel="noopener" style="margin-top:14px">{{ __('dashboard.pages.payment.pay_tripay') }}</a>@endif
        @if($payment->status === 'PAID')<div class="badge green" style="margin-top:14px">{{ __('dashboard.pages.payment.success') }}</div>@endif
    </div>
    <div class="card">
        <h3>{{ __('dashboard.pages.payment.summary') }}</h3>
        <div class="pay-row"><span>{{ __('dashboard.pages.payment.balance_amount') }}</span><strong>Rp {{ number_format($payment->amount_idr, 0, ',', '.') }}</strong></div>
        <div class="pay-row"><span>{{ __('dashboard.pages.payment.fee') }}</span><strong>Rp {{ number_format($payment->fee_customer, 0, ',', '.') }}</strong></div>
        <div class="pay-row"><span>{{ __('dashboard.pages.payment.total') }}</span><strong>Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</strong></div>
        @if((float) $payment->bonus_usd > 0)
        <div class="pay-row" style="color:var(--green-ink)"><span>{{ __('dashboard.pages.payment.bonus') }}</span><strong>+ Rp {{ number_format($payment->bonus_idr, 0, ',', '.') }}</strong></div>
        @endif
        <div class="pay-row"><span>{{ __('dashboard.pages.payment.received') }}</span><strong>${{ number_format((float) $payment->credit_usd + (float) $payment->bonus_usd, 6) }}</strong></div>
        <div class="pay-row"><span>{{ __('dashboard.pages.payment.expires') }}</span><strong>{{ $payment->expires_at?->locale(app()->getLocale())->translatedFormat('d M Y H:i') ?? '—' }}</strong></div>
        <a class="btn secondary" href="{{ route('billing') }}" style="margin-top:14px">{{ __('dashboard.pages.payment.back') }}</a>
    </div>
</div>
@endsection

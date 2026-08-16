@extends('layouts.app')

@section('content')
<style>
    /* ===== Halaman Billing PAYG ===== */
    .payg-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
    .stat{display:flex;align-items:center;gap:12px;padding:16px 18px}
    .stat-ic{width:38px;height:38px;border-radius:11px;background:var(--brand-soft);border:1px solid var(--brand-line);color:var(--brand);display:grid;place-items:center;flex:0 0 auto}
    .stat-ic svg{width:18px;height:18px}
    .stat-ic.ic-green{background:var(--green-soft);border-color:var(--green-line);color:var(--green-ink)}
    .stat-ic.ic-amber{background:var(--amber-soft);border-color:var(--amber-line);color:var(--amber-ink)}
    .stat-ic.ic-slate{background:var(--soft);border-color:var(--line);color:var(--muted)}
    .stat-lbl{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
    .stat .metric{margin-top:2px}
    .stat-sub{font-size:11.5px;color:var(--muted);margin-top:2px}

    .calc-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .calc-result{margin-top:14px;background:var(--green-soft);border:1px solid var(--green-line);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px}
    .calc-result .lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--green-ink)}
    .calc-result .val{font-size:18px;font-weight:800;color:var(--green-ink);font-variant-numeric:tabular-nums}
    .calc-hint{font-size:12px;color:var(--muted);margin-top:8px}

    .price-note{font-size:12px;color:var(--muted);margin:8px 0 0;line-height:1.6}
    .original-price{display:block;color:var(--muted);font-size:11px;text-decoration:line-through}

    @media (min-width:640px) and (max-width:1099.98px){
        .payg-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:639.98px){
        .payg-stats{grid-template-columns:minmax(0,1fr)}
        .calc-row{grid-template-columns:1fr}
    }
</style>

<div class="top">
    <div><h2>{{ __('dashboard.titles.billing') }}</h2><p>{{ __('dashboard.pages.billing.subtitle') }}</p></div>
    <span class="pill">PAYG · IDR</span>
</div>

<div class="hero">
    <h2>{{ format_idr_usd($user->balance) }}</h2>
    <p>{{ __('dashboard.pages.billing.balance_text') }}</p>
    <div style="margin-top:14px"><a class="btn" href="{{ route('payments.create') }}">{{ __('dashboard.pages.billing.topup') }}</a></div>
    <p class="price-note" style="margin-top:10px">{!! __('dashboard.pages.billing.rate', ['rate' => '<strong>Rp '.number_format(usd_to_idr_rate(), 0, ',', '.').'</strong>']) !!}</p>
</div>

<div class="payg-stats">
    <div class="card stat">
        <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20v-6"/><path d="M12 20V8"/><path d="M18 20v-10"/><path d="M3 20h18"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.pages.billing.month_requests') }}</div><div class="metric small">{{ number_format($monthRequests) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.pages.billing.month_tokens') }}</div><div class="metric small">{{ format_compact_number($monthTokens) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.pages.billing.month_cost') }}</div><div class="metric small">{{ format_idr_usd($monthCost) }}</div><div class="stat-sub">{{ __('dashboard.pages.billing.average_request', ['cost' => format_idr_usd($avgCostPerRequest)]) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-slate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.pages.billing.estimated_requests') }}</div><div class="metric small">{{ $estimatedRequests !== null ? number_format($estimatedRequests, 0, ',', '.') : '—' }}</div><div class="stat-sub">{{ __('dashboard.pages.billing.estimate_basis') }}</div></div>
    </div>
</div>

<div class="section grid2">
    <div class="card">
        <h3>{{ __('dashboard.pages.billing.model_prices') }}</h3>
        @if($models->isNotEmpty())
            <div class="table-wrap"><table>
                <tr><th>{{ __('dashboard.common.model') }}</th><th>{{ __('dashboard.pages.billing.input_1m') }}</th><th>{{ __('dashboard.pages.billing.output_1m') }}</th><th>{{ __('dashboard.pages.billing.cache_read_1m') }}</th><th>{{ __('dashboard.pages.billing.cache_write_1m') }}</th></tr>
                @foreach($models as $model)
                    @php($rule = $pricingRules->get($model->id))
                    <tr>
                        <td><strong>{{ $model->public_name }}</strong></td>
                        <td>@if($rule?->promo_is_active && $rule->original_input_per_million !== null)<span class="original-price">{{ format_idr_usd($rule->original_input_per_million) }}</span>@endif{{ $rule ? format_idr_usd($rule->effective_input_price) : '—' }}</td>
                        <td>@if($rule?->promo_is_active && $rule->original_output_per_million !== null)<span class="original-price">{{ format_idr_usd($rule->original_output_per_million) }}</span>@endif{{ $rule ? format_idr_usd($rule->effective_output_price) : '—' }}</td>
                        <td>{{ $rule?->cache_read_input_per_million !== null ? format_idr_usd($rule->cache_read_input_per_million) : '—' }}</td>
                        <td>{{ $rule?->cache_write_per_million !== null ? format_idr_usd($rule->cache_write_per_million) : '—' }}</td>
                    </tr>
                @endforeach
            </table></div>
        @else
            <p class="muted">{{ __('dashboard.pages.billing.no_models') }}</p>
        @endif
    </div>
    <div class="card">
        <h3>{{ __('dashboard.pages.billing.calculator') }}</h3>
        <form class="compact" id="calc-form" onsubmit="return false">
            <label class="muted">{{ __('dashboard.common.model') }}</label>
            <select id="calc-model">
                @foreach($models as $model)
                    <option value="{{ $model->id }}" data-in="{{ $pricingRules->get($model->id)?->effective_input_price ?? 0 }}" data-out="{{ $pricingRules->get($model->id)?->effective_output_price ?? 0 }}">{{ $model->public_name }}</option>
                @endforeach
            </select>
            <div class="calc-row">
                <div><label class="muted">{{ __('dashboard.pages.billing.input_tokens') }}</label><input id="calc-in" type="number" min="0" value="1000" placeholder="{{ __('dashboard.pages.billing.example', ['value' => '1000']) }}"></div>
                <div><label class="muted">{{ __('dashboard.pages.billing.output_tokens') }}</label><input id="calc-out" type="number" min="0" value="500" placeholder="{{ __('dashboard.pages.billing.example', ['value' => '500']) }}"></div>
            </div>
            <div class="calc-result">
                <span class="lbl">{{ __('dashboard.pages.billing.request_estimate') }}</span>
                <span class="val" id="calc-total">—</span>
            </div>
            <p class="calc-hint" id="calc-total-usd" style="margin-top:6px"></p>
            <p class="calc-hint">{{ __('dashboard.pages.billing.estimate_hint') }}</p>
        </form>
    </div>
</div>

<div class="section card">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px"><h3 style="margin:0">{{ __('dashboard.pages.billing.topup_history') }}</h3><a class="btn secondary" href="{{ route('payments.create') }}">{{ __('dashboard.pages.billing.new_topup') }}</a></div>
    <div class="table-wrap"><table>
        <tr><th>{{ __('dashboard.common.date') }}</th><th>{{ __('dashboard.pages.billing.reference') }}</th><th>{{ __('dashboard.pages.billing.method') }}</th><th>{{ __('dashboard.pages.billing.nominal') }}</th><th>{{ __('dashboard.pages.billing.total_payment') }}</th><th>{{ __('dashboard.topbar.balance') }}</th><th>{{ __('dashboard.common.status') }}</th><th>{{ __('dashboard.common.action') }}</th></tr>
        @forelse($paymentOrders as $payment)
            @php($statusClass = $payment->status === 'PAID' ? 'green' : ($payment->status === 'UNPAID' ? 'amber' : 'red'))
            <tr>
                <td>{{ $payment->created_at?->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $payment->merchant_ref }}</td>
                <td>{{ $payment->payment_name ?? $payment->payment_method }}</td>
                <td>Rp {{ number_format($payment->amount_idr, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($payment->total_amount ?: $payment->amount_idr, 0, ',', '.') }}</td>
                <td>${{ number_format((float) $payment->credit_usd, 6) }}</td>
                <td><span class="badge {{ $statusClass }}">{{ __('dashboard.status.'.strtolower($payment->status)) }}</span></td>
                <td><a class="btn secondary" href="{{ route('payments.show', $payment) }}" style="padding:5px 9px;font-size:11.5px">{{ __('dashboard.common.detail') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:var(--muted)">{{ __('dashboard.pages.billing.empty_topup') }}</td></tr>
        @endforelse
    </table></div>
    @if($paymentOrders->hasMorePages())
        <div class="section" style="text-align:center"><a class="btn secondary" href="{{ $paymentOrders->nextPageUrl() }}">{{ __('dashboard.common.view_more') }}</a></div>
    @endif
</div>

<div class="section card">
    <h3>{{ __('dashboard.pages.billing.latest_ledger') }}</h3>
    <div class="table-wrap"><table>
        <tr><th>{{ __('dashboard.common.date') }}</th><th>{{ __('dashboard.common.type') }}</th><th>{{ __('dashboard.common.amount') }}</th><th>{{ __('dashboard.pages.billing.balance_after') }}</th><th>{{ __('dashboard.common.status') }}</th><th>{{ __('dashboard.common.notes') }}</th></tr>
        @forelse($transactions as $transaction)
            <tr>
                <td>{{ $transaction->created_at?->locale(app()->getLocale())->translatedFormat('d M Y H:i') }}</td>
                <td>{{ $transaction->type }}</td>
                <td>{{ format_idr_usd($transaction->amount) }}</td>
                <td>{{ format_idr_usd($transaction->balance_after) }}</td>
                <td><span class="badge {{ $transaction->status === 'completed' ? 'green' : 'amber' }}">{{ __('dashboard.status.'.strtolower($transaction->status)) }}</span></td>
                <td>{{ $transaction->notes }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:var(--muted)">{{ __('dashboard.pages.billing.empty_ledger') }}</td></tr>
        @endforelse
    </table></div>
    @if($transactions->hasMorePages())
        <div class="section" style="text-align:center"><a class="btn secondary" href="{{ $transactions->nextPageUrl() }}">{{ __('dashboard.common.view_more') }}</a></div>
    @endif
</div>

<script>
(function () {
    var modelSel = document.getElementById('calc-model');
    var inTok = document.getElementById('calc-in');
    var outTok = document.getElementById('calc-out');
    var total = document.getElementById('calc-total');

    if (!modelSel) return;

    var rate = {{ number_format(usd_to_idr_rate(), 2, '.', '') }};
    function fmt(n) {
        var opts = n >= 1000 ? {maximumFractionDigits: 0} : {minimumFractionDigits: 2, maximumFractionDigits: 4};
        return 'Rp ' + n.toLocaleString('id-ID', opts);
    }
    function calc() {
        if (!modelSel || !modelSel.options.length) { total.textContent = '—'; return; }
        var opt = modelSel.selectedOptions[0];
        var inP = parseFloat(opt.getAttribute('data-in')) || 0;
        var outP = parseFloat(opt.getAttribute('data-out')) || 0;
        var i = Math.max(0, parseFloat(inTok.value) || 0);
        var o = Math.max(0, parseFloat(outTok.value) || 0);
        var cost = ((i / 1000000 * inP) + (o / 1000000 * outP));
        total.textContent = fmt(cost * rate);
        var usdHint = document.getElementById('calc-total-usd');
        if (usdHint) usdHint.textContent = '$' + cost.toFixed(4) + ' (kurs 1 USD = Rp ' + rate.toLocaleString('id-ID', {maximumFractionDigits: 0}) + ')';
    }
    modelSel.addEventListener('change', calc);
    inTok.addEventListener('input', calc);
    outTok.addEventListener('input', calc);
    calc();
})();
</script>
@endsection

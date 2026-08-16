@extends('layouts.app')

@section('content')
<style>
    .plan-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .plan-card{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:14px;background:var(--panel);padding:18px;box-shadow:var(--shadow-card);transition:box-shadow .18s,border-color .18s}
    .plan-card:hover{box-shadow:var(--shadow-hover);border-color:var(--line-strong)}
    .plan-card h3{margin:0 0 2px;font-size:15.5px;font-weight:800;letter-spacing:-.01em}
    .plan-card .desc{color:var(--muted);font-size:12.5px;margin:0 0 14px;min-height:34px}
    /* Chip model di kartu plan */
    .plan-chips{display:flex;flex-wrap:wrap;gap:4px;align-items:center;margin:0 0 12px}
    .plan-chip{display:inline-flex;align-items:center;gap:5px;max-width:170px;background:var(--soft);border:1px solid var(--line);border-radius:999px;padding:2px 8px;font-size:11.5px;font-weight:600;color:var(--body);white-space:nowrap;overflow:hidden}
    .plan-chip img{width:14px;height:14px;border-radius:4px;object-fit:contain;flex:0 0 auto}
    .plan-chip .pc-name{overflow:hidden;text-overflow:ellipsis}
    .plan-chip.more{cursor:pointer;background:var(--brand-soft);border-color:var(--brand-line);color:var(--brand);font-weight:700}
    .plan-chip.more:hover{background:var(--brand-line);color:var(--brand)}
    .plan-chips-hidden{display:flex;flex-wrap:wrap;gap:4px;align-items:center}
    .plan-chips-hidden[hidden]{display:none}
    .plan-metric{display:flex;justify-content:space-between;align-items:baseline;padding:7px 0;border-top:1px dashed var(--line);font-size:13px}
    .plan-metric span{color:var(--muted);font-size:12px}
    .plan-metric strong{font-size:13.5px;font-variant-numeric:tabular-nums}
    .plan-price{margin:auto 0 0;padding-top:14px}
    .plan-price .usd{font-size:22px;font-weight:800;letter-spacing:-.02em}
    .plan-price .idr{color:var(--muted);font-size:12.5px}
    .plan-price form{margin-top:10px}
    .plan-price button{width:100%}
    .payg-box{display:flex;align-items:center;gap:14px;justify-content:space-between;flex-wrap:wrap}
    .payg-box .badge{margin-top:4px}
    /* Breakpoint mengikuti layout global (tablet <1000px, phone <640px). */
    @media(max-width:999.98px){.plan-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:639.98px){.plan-grid{grid-template-columns:minmax(0,1fr)}}
    /* Riwayat plan: kartu di phone, tabel di tablet/desktop. */
    .plan-hist-cards{display:none}
    @media(max-width:639.98px){
        .plan-hist-table{display:none}
        .plan-hist-cards{display:grid;gap:12px}
        .plan-hist-card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:14px}
        .phc-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
        .phc-head strong{font-size:14px;font-weight:750;letter-spacing:-.01em}
        .phc-meta{color:var(--muted);font-size:12px;margin:1px 0 10px}
        .phc-bar{height:6px;border-radius:999px;background:var(--line);overflow:hidden}
        .phc-bar > div{height:100%;border-radius:999px;background:var(--brand);transition:width .3s}
        .phc-bar-label{color:var(--muted);font-size:11.5px;margin-top:5px;text-align:right}
        .phc-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px;margin-top:12px;border-top:1px dashed var(--line);padding-top:10px;font-size:12.5px}
        .phc-grid span{display:block;color:var(--muted);font-size:11px}
        .phc-grid strong{font-size:13px;font-variant-numeric:tabular-nums}
    }
    /* Modal konfirmasi pembelian. */
    .modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:none;align-items:center;justify-content:center;padding:20px}
    .modal-backdrop.open{display:flex}
    .modal{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(15,23,42,.25);animation:fadeUp .18s ease}
    .modal h3{margin:0 0 14px;font-size:16px;font-weight:800;letter-spacing:-.01em}
    .buy-summary{display:flex;flex-direction:column;gap:9px;background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:14px;margin:0 0 14px}
    .buy-row{display:flex;align-items:baseline;justify-content:space-between;gap:14px;font-size:13.5px}
    .buy-row span{color:var(--muted);font-size:12px;flex:0 0 auto}
    .buy-row strong{font-variant-numeric:tabular-nums;text-align:right}
    .buy-row.price strong{font-size:15px;font-weight:800}
    .buy-hint{margin:0 0 4px;color:var(--muted);font-size:12.5px;line-height:1.5}
    .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
    @keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
    @media(max-width:639.98px){.modal-actions{flex-direction:column-reverse}.modal-actions .btn,.modal-actions button{width:100%}}
</style>

<div class="top">
    <div><h2>{{ __('dashboard.pages.plans.heading') }}</h2><p>{{ __('dashboard.pages.plans.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.pages.plans.balance') }}: {{ format_idr_usd($user->balance) }}</span>
</div>

@if(session('success'))
    <div class="set-success" style="background:var(--green-soft);border:1px solid var(--green-line);color:var(--green-ink);border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;margin-bottom:16px">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="error">{{ $errors->first() }}</div>
@endif

<div class="card" style="margin-bottom:16px">
    <div class="payg-box">
        <div>
            <h3 style="margin:0 0 2px">{{ __('dashboard.pages.plans.payg_title') }}</h3>
            <p class="muted" style="margin:0;font-size:13px;max-width:640px">{{ __('dashboard.pages.plans.payg_hint') }}</p>
            <span class="badge {{ $user->payg_enabled ? 'green' : 'amber' }}" style="margin-top:8px">{{ $user->payg_enabled ? __('dashboard.pages.plans.payg_on') : __('dashboard.pages.plans.payg_off') }}</span>
        </div>
        <form method="post" action="{{ route('settings.payg') }}">
            @csrf @method('patch')
            <input type="hidden" name="payg_enabled" value="{{ $user->payg_enabled ? 0 : 1 }}">
            <button class="secondary">{{ $user->payg_enabled ? __('dashboard.pages.plans.payg_disable') : __('dashboard.pages.plans.payg_enable') }}</button>
        </form>
    </div>
    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-top:14px;border-top:1px dashed var(--line);padding-top:12px">
        <div><span class="muted" style="font-size:12px">{{ __('dashboard.pages.plans.plan_tokens') }}</span><br><strong style="font-size:15px">{{ format_compact_number($activePlanTokens) }}</strong></div>
        <div><span class="muted" style="font-size:12px">{{ __('dashboard.pages.plans.price_idr') }} (pair)</span><br><strong style="font-size:15px">1 USD = Rp {{ number_format($usdRate, 0, ',', '.') }}</strong></div>
    </div>
</div>

@if($freePlan)
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h3 style="margin:0 0 2px">{{ $freePlan->plan?->name ?? 'Free' }} <span class="badge green" style="margin-left:6px">{{ __('dashboard.pages.plans.free_badge') }}</span></h3>
            <p class="muted" style="margin:0;font-size:13px">{{ __('dashboard.pages.plans.free_hint') }}</p>
        </div>
        <div style="text-align:right">
            <div style="font-size:22px;font-weight:800;letter-spacing:-.02em">{{ format_compact_number($freePlan->remaining_tokens) }}</div>
            <div class="muted" style="font-size:12px">{{ __('dashboard.pages.plans.free_remaining') }} · {{ __('dashboard.pages.plans.free_per_day', ['tokens' => format_compact_number($freePlan->daily_limit_tokens)]) }}</div>
        </div>
    </div>
    @if($freePlan->quota_tokens > 0)
    <div style="margin-top:14px;border-top:1px dashed var(--line);padding-top:12px">
        <div style="height:8px;border-radius:999px;background:var(--line);overflow:hidden">
            <div style="height:100%;border-radius:999px;background:var(--brand);width:{{ $freePlan->remaining_percent }}%;transition:width .3s"></div>
        </div>
        <div class="muted" style="font-size:11.5px;margin-top:5px;display:flex;justify-content:space-between">
            <span>{{ __('dashboard.pages.plans.tokens_left', ['used' => format_compact_number($freePlan->remaining_tokens), 'total' => format_compact_number($freePlan->quota_tokens)]) }}</span>
            <span>{{ $freePlan->remaining_percent }}%</span>
        </div>
    </div>
    @endif
</div>
@endif

<div class="section">
    <h3 style="font-size:16px;font-weight:800;letter-spacing:-.01em;margin:0 0 12px">{{ __('dashboard.pages.plans.purchase_title') }}</h3>
    @if($available->isEmpty())
        <div class="card muted" style="text-align:center">{{ __('dashboard.pages.plans.no_plans') }}</div>
    @else
        <div class="plan-grid">
            @foreach($available as $plan)
            <div class="plan-card">
                <h3>{{ $plan->name }}</h3>
                <p class="desc">{{ $plan->description }}</p>
                @if($plan->models->isNotEmpty())
                <div class="plan-chips">
                    @foreach($plan->models->take(3) as $model)
                    <span class="plan-chip" title="{{ $model->public_name }}">@if($model->icon_url)<img src="{{ $model->icon_url }}" alt="">@endif<span class="pc-name">{{ $model->public_name }}</span></span>
                    @endforeach
                    @if($plan->models->count() > 3)
                    <span class="plan-chip more" role="button" tabindex="0" data-more-chips title="Klik untuk menampilkan semua ({{ $plan->models->count() }} model)">+{{ $plan->models->count() - 3 }}</span>
                    <span class="plan-chips-hidden" hidden>
                        @foreach($plan->models->skip(3) as $model)
                        <span class="plan-chip" title="{{ $model->public_name }}">@if($model->icon_url)<img src="{{ $model->icon_url }}" alt="">@endif<span class="pc-name">{{ $model->public_name }}</span></span>
                        @endforeach
                    </span>
                    @endif
                </div>
                @endif
                <div class="plan-metric"><span>{{ __('dashboard.pages.plans.tokens_total') }}</span><strong>{{ $plan->tokens_label }}</strong></div>
                @if($plan->daily_limit_label)<div class="plan-metric"><span>{{ __('dashboard.pages.plans.daily_limit') }}</span><strong>{{ $plan->daily_limit_label }}</strong></div>@endif
                @if($plan->rate_limit_per_minute)<div class="plan-metric"><span>{{ __('dashboard.pages.plans.rate_limit') }}</span><strong>{{ $plan->rate_limit_per_minute }}/menit</strong></div>@endif
                <div class="plan-metric"><span>{{ __('dashboard.pages.plans.duration') }}</span><strong>{{ $plan->duration_label }}</strong></div>
                @if($plan->stock !== null)
                <div class="plan-metric"><span>{{ __('dashboard.pages.plans.stock') }}</span><strong @if($plan->is_sold_out) style="color:var(--red-ink)" @endif>{{ $plan->is_sold_out ? __('dashboard.pages.plans.sold_out') : __('dashboard.pages.plans.stock_left', ['stock' => $plan->stock]) }}</strong></div>
                @endif
                <div class="plan-price">
                    <div class="usd">{{ format_usd($plan->price_usd) }}</div>
                    @if($plan->price_idr)<div class="idr">≈ Rp {{ number_format($plan->price_idr, 0, ',', '.') }}</div>@endif
                    <form id="buy-form-{{ $plan->id }}" method="post" action="{{ route('plans.purchase', $plan) }}">
                        @csrf
                        @if($plan->is_sold_out)
                        <button disabled style="opacity:.5;cursor:not-allowed">{{ __('dashboard.pages.plans.sold_out') }}</button>
                        @else
                        <button type="button" class="buy-trigger" data-plan="{{ $plan->id }}"
                            data-name="{{ $plan->name }}"
                            data-tokens="{{ $plan->tokens_label }}"
                            data-daily="{{ $plan->daily_limit_label }}"
                            data-rate="{{ $plan->rate_limit_per_minute ? $plan->rate_limit_per_minute.'/menit' : '' }}"
                            data-duration="{{ $plan->duration_label }}"
                            data-models="{{ $plan->models->pluck('public_name')->join(', ') }}"
                            data-stock="{{ $plan->stock !== null ? __('dashboard.pages.plans.stock_left', ['stock' => $plan->stock]) : '' }}"
                            data-price-usd="{{ format_usd($plan->price_usd) }}"
                            data-price-idr="{{ $plan->price_idr ? '≈ Rp ' . number_format($plan->price_idr, 0, ',', '.') : '' }}"
                            data-hint="{{ __('dashboard.pages.plans.buy_hint', ['price' => format_usd($plan->price_usd)]) }}">{{ __('dashboard.pages.plans.buy') }}</button>
                        @endif
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

<div class="section card">
    <h3>{{ __('dashboard.pages.plans.history_title') }}</h3>
    <div class="plan-hist-table table-wrap"><table>
        <tr><th>{{ __('dashboard.pages.plans.plan_col') }}</th><th>{{ __('dashboard.pages.plans.remaining') }}</th><th>{{ __('dashboard.pages.plans.used') }}</th><th>{{ __('dashboard.pages.plans.usage') }}</th><th>{{ __('dashboard.pages.plans.purchased') }}</th><th>{{ __('dashboard.pages.plans.expires') }}</th><th>{{ __('dashboard.pages.plans.status_label') }}</th></tr>
        @forelse($userPlans as $userPlan)
        <tr>
            <td><strong>{{ $userPlan->plan?->name ?? '—' }}</strong><br><span class="muted" style="font-size:12px">{{ $userPlan->plan?->tokens_label }}</span></td>
            <td>{{ format_compact_number(max(0, $userPlan->tokens_remaining)) }}</td>
            <td>{{ format_compact_number($userPlan->tokens_used) }}</td>
            <td style="min-width:150px">
                @if($userPlan->quota_tokens > 0)
                <div style="height:6px;border-radius:999px;background:var(--line);overflow:hidden;margin-bottom:4px">
                    <div style="height:100%;border-radius:999px;background:var(--brand);width:{{ $userPlan->remaining_percent }}%;transition:width .3s"></div>
                </div>
                <span class="muted" style="font-size:11.5px">{{ __('dashboard.pages.plans.percent_left', ['percent' => $userPlan->remaining_percent]) }}</span>
                @else
                <span class="muted">—</span>
                @endif
            </td>
            <td>{{ $userPlan->purchased_at?->format('d M Y H:i') }}</td>
            <td>{{ $userPlan->expires_at?->format('d M Y H:i') ?? __('dashboard.pages.plans.no_expiry') }}</td>
            <td>
                @if($userPlan->is_expired)
                    <span class="badge red">{{ __('dashboard.pages.plans.expired') }}</span>
                @elseif($userPlan->resets_daily)
                    <span class="badge green">{{ __('dashboard.pages.plans.free_active') }}</span>
                @elseif($userPlan->tokens_remaining <= 0)
                    <span class="badge amber">{{ __('dashboard.status.degraded') }}</span>
                @else
                    <span class="badge green">{{ __('dashboard.common.active') }}</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--muted)">{{ __('dashboard.pages.plans.empty_history') }}</td></tr>
        @endforelse
    </table></div>
    <div class="plan-hist-cards">
        @forelse($userPlans as $userPlan)
        <div class="plan-hist-card">
            <div class="phc-head">
                <strong>{{ $userPlan->plan?->name ?? '—' }}</strong>
                @if($userPlan->is_expired)
                    <span class="badge red">{{ __('dashboard.pages.plans.expired') }}</span>
                @elseif($userPlan->resets_daily)
                    <span class="badge green">{{ __('dashboard.pages.plans.free_active') }}</span>
                @elseif($userPlan->tokens_remaining <= 0)
                    <span class="badge amber">{{ __('dashboard.status.degraded') }}</span>
                @else
                    <span class="badge green">{{ __('dashboard.common.active') }}</span>
                @endif
            </div>
            <div class="phc-meta">{{ $userPlan->plan?->tokens_label }}</div>
            @if($userPlan->quota_tokens > 0)
            <div class="phc-bar"><div style="width:{{ $userPlan->remaining_percent }}%"></div></div>
            <div class="phc-bar-label">{{ $userPlan->remaining_percent }}% {{ __('dashboard.pages.plans.remaining') }}</div>
            @endif
            <div class="phc-grid">
                <div><span>{{ __('dashboard.pages.plans.remaining') }}</span><strong>{{ format_compact_number(max(0, $userPlan->tokens_remaining)) }}</strong></div>
                <div><span>{{ __('dashboard.pages.plans.used') }}</span><strong>{{ format_compact_number($userPlan->tokens_used) }}</strong></div>
                <div><span>{{ __('dashboard.pages.plans.purchased') }}</span><strong>{{ $userPlan->purchased_at?->format('d M Y H:i') }}</strong></div>
                <div><span>{{ __('dashboard.pages.plans.expires') }}</span><strong>{{ $userPlan->expires_at?->format('d M Y H:i') ?? __('dashboard.pages.plans.no_expiry') }}</strong></div>
            </div>
        </div>
        @empty
        <div class="card muted" style="text-align:center">{{ __('dashboard.pages.plans.empty_history') }}</div>
        @endforelse
    </div>
    <div style="margin-top:14px">{{ $userPlans->links() }}</div>
</div>

<div class="modal-backdrop" id="buy-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="buy-modal-title">
        <h3 id="buy-modal-title">{{ __('dashboard.pages.plans.confirm_title') }}</h3>
        <div class="buy-summary">
            <div class="buy-row"><span>{{ __('dashboard.common.name') }}</span><strong id="buy-name"></strong></div>
            <div class="buy-row"><span>{{ __('dashboard.pages.plans.tokens_total') }}</span><strong id="buy-tokens"></strong></div>
            <div class="buy-row" id="buy-daily-row" hidden><span>{{ __('dashboard.pages.plans.daily_limit') }}</span><strong id="buy-daily"></strong></div>
            <div class="buy-row" id="buy-rate-row" hidden><span>{{ __('dashboard.pages.plans.rate_limit') }}</span><strong id="buy-rate"></strong></div>
            <div class="buy-row"><span>{{ __('dashboard.pages.plans.duration') }}</span><strong id="buy-duration"></strong></div>
            <div class="buy-row" id="buy-models-row" hidden><span>{{ __('dashboard.common.model') }}</span><strong id="buy-models"></strong></div>
            <div class="buy-row" id="buy-stock-row" hidden><span>{{ __('dashboard.pages.plans.stock') }}</span><strong id="buy-stock"></strong></div>
            <div class="buy-row price"><span>{{ __('dashboard.pages.plans.price_usd') }}</span><strong id="buy-price-usd"></strong></div>
            <div class="buy-row" id="buy-price-idr-row" hidden><span>{{ __('dashboard.pages.plans.price_idr') }}</span><strong id="buy-price-idr"></strong></div>
        </div>
        <p class="buy-hint" id="buy-hint"></p>
        <div class="modal-actions">
            <button class="btn secondary" data-close-buy>{{ __('dashboard.common.cancel') }}</button>
            <button class="btn" data-confirm-buy>{{ __('dashboard.pages.plans.confirm_yes') }}</button>
        </div>
    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('buy-modal');
    if (!backdrop) return;

    var closeBtn = backdrop.querySelector('[data-close-buy]');
    var confirmBtn = backdrop.querySelector('[data-confirm-buy]');
    var fields = {
        name: document.getElementById('buy-name'),
        tokens: document.getElementById('buy-tokens'),
        daily: document.getElementById('buy-daily'),
        dailyRow: document.getElementById('buy-daily-row'),
        rate: document.getElementById('buy-rate'),
        rateRow: document.getElementById('buy-rate-row'),
        duration: document.getElementById('buy-duration'),
        models: document.getElementById('buy-models'),
        modelsRow: document.getElementById('buy-models-row'),
        stock: document.getElementById('buy-stock'),
        stockRow: document.getElementById('buy-stock-row'),
        priceUsd: document.getElementById('buy-price-usd'),
        priceIdr: document.getElementById('buy-price-idr'),
        priceIdrRow: document.getElementById('buy-price-idr-row'),
        hint: document.getElementById('buy-hint')
    };
    var form = null;
    var lastTrigger = null;

    function setRow(valueEl, rowEl, value) {
        if (value) {
            valueEl.textContent = value;
            rowEl.hidden = false;
        } else {
            valueEl.textContent = '';
            rowEl.hidden = true;
        }
    }

    function openBuy(trigger) {
        form = document.getElementById('buy-form-' + trigger.getAttribute('data-plan'));
        fields.name.textContent = trigger.getAttribute('data-name') || '';
        fields.tokens.textContent = trigger.getAttribute('data-tokens') || '—';
        setRow(fields.daily, fields.dailyRow, trigger.getAttribute('data-daily'));
        setRow(fields.rate, fields.rateRow, trigger.getAttribute('data-rate'));
        fields.duration.textContent = trigger.getAttribute('data-duration') || '—';
        setRow(fields.models, fields.modelsRow, trigger.getAttribute('data-models'));
        setRow(fields.stock, fields.stockRow, trigger.getAttribute('data-stock'));
        fields.priceUsd.textContent = trigger.getAttribute('data-price-usd') || '—';
        setRow(fields.priceIdr, fields.priceIdrRow, trigger.getAttribute('data-price-idr'));
        fields.hint.textContent = trigger.getAttribute('data-hint') || '';
        lastTrigger = trigger;
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        closeBtn.focus();
    }

    function closeBuy() {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        if (lastTrigger) lastTrigger.focus();
    }

    document.addEventListener('click', function (e) {
        if (!(e.target instanceof Element)) return;
        var trigger = e.target.closest('.buy-trigger');
        if (trigger) openBuy(trigger);
    });

    confirmBtn.addEventListener('click', function () {
        if (!form) return;
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
    });
    closeBtn.addEventListener('click', closeBuy);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closeBuy(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeBuy(); });
})();

(function () {
    // Chip "+N" model di kartu plan: klik untuk memperlihatkan sisa model.
    document.querySelectorAll('[data-more-chips]').forEach(function (chip) {
        function expand() {
            var hidden = chip.nextElementSibling;
            if (!hidden || !hidden.hidden) return;
            hidden.hidden = false;
            chip.style.display = 'none';
        }
        chip.addEventListener('click', expand);
        chip.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                expand();
            }
        });
    });
})();
</script>
@endsection

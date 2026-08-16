@extends('layouts.app')

@section('content')
<style>
    .channel-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .channel{position:relative}
    .channel input{position:absolute;opacity:0;pointer-events:none}
    .channel label{display:flex;align-items:center;gap:10px;height:100%;padding:13px;border:1px solid var(--line);border-radius:11px;background:var(--panel);cursor:pointer;transition:.15s}
    .channel input:checked+label{border-color:var(--brand);background:var(--brand-soft);box-shadow:0 0 0 2px var(--brand-line)}
    .channel img{width:40px;height:28px;object-fit:contain;background:#fff;border-radius:5px;padding:2px}
    .channel strong{display:block;font-size:12.5px}.channel span{display:block;font-size:10.5px;color:var(--muted)}
    @media(max-width:800px){.channel-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:520px){.channel-grid{grid-template-columns:minmax(0,1fr)}}
</style>
<div class="top"><div><h2>{{ __('dashboard.pages.topup.heading') }}</h2><p>{{ __('dashboard.pages.topup.subtitle') }}</p></div><a class="btn secondary" href="{{ route('billing') }}">{{ __('dashboard.pages.topup.back') }}</a></div>
@if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
@if($error)<div class="error">{{ $error }} {{ __('dashboard.pages.topup.gateway_hint') }}</div>@endif
<div class="card">
    <form method="post" action="{{ route('payments.store') }}">@csrf
        <label class="muted">{{ __('dashboard.pages.topup.amount') }}</label>
        <input name="amount" type="number" min="{{ $minimumTopup }}" max="10000000" step="1000" value="{{ old('amount', 50000) }}" required>
        <p class="muted" style="font-size:12px;margin:-5px 0 16px">{{ __('dashboard.pages.topup.minimum', ['amount' => 'Rp '.number_format($minimumTopup, 0, ',', '.')]) }}</p>
        <label class="muted">{{ __('dashboard.pages.topup.phone') }}</label>
        <input name="customer_phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('customer_phone') }}" placeholder="{{ __('dashboard.pages.topup.phone_example') }}" required>
        <p class="muted" style="font-size:12px;margin:-5px 0 16px">{{ __('dashboard.pages.topup.phone_hint') }}</p>
        <label class="muted">{{ __('dashboard.pages.topup.method') }}</label>
        <div class="channel-grid">
            @foreach($channels as $channel)
            <div class="channel">
                <input id="channel-{{ $channel['code'] }}" name="method" type="radio" value="{{ $channel['code'] }}" @checked(old('method') === $channel['code']) required>
                <label for="channel-{{ $channel['code'] }}">
                    <img src="{{ $channel['icon_url'] }}" alt="">
                    <span><strong>{{ $channel['name'] }}</strong><span>{{ __('dashboard.pages.topup.channel_minimum', ['group' => $channel['group'], 'amount' => 'Rp '.number_format($channel['minimum_amount'] ?? 0, 0, ',', '.')]) }}</span></span>
                </label>
            </div>
            @endforeach
        </div>
        @if(count($channels))<button style="margin-top:18px">{{ __('dashboard.pages.topup.continue') }}</button>@endif
    </form>
</div>
@endsection

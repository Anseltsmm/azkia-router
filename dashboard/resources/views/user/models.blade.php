@extends('layouts.app')

@section('content')
<style>
    /* ===== Halaman Models ===== */
    .models-toolbar{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
    .models-sort{width:auto;margin:0;font-size:13px;flex:0 0 auto}
    .models-search{position:relative;flex:1;min-width:250px}
    .models-search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--muted);pointer-events:none}
    .models-search input{padding-left:36px;margin:0}
    .models-filters{display:flex;gap:8px;flex-wrap:wrap}
    .chip{display:inline-flex;align-items:center;gap:6px;background:var(--panel);border:1px solid var(--line);color:var(--body);border-radius:999px;padding:6px 13px;font-size:12.5px;font-weight:650;cursor:pointer;transition:border-color .13s,color .13s,background .13s,box-shadow .13s,transform .1s}
    .chip:hover{border-color:var(--line-strong);color:var(--ink)}
    .chip:active{transform:scale(.97)}
    .chip.active{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 3px 10px rgba(37,99,235,.28)}
    .chip .n{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--soft);color:var(--muted);font-size:11px;font-weight:700}
    .chip.active .n{background:rgba(255,255,255,.22);color:#fff}

    .models-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .model-card{position:relative;display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:12px 13px;box-shadow:var(--shadow-card);transition:box-shadow .18s,border-color .18s,transform .18s;min-width:0}
    .model-card:hover{box-shadow:var(--shadow-hover);border-color:var(--line-strong);transform:translateY(-2px)}
    .model-head{display:flex;align-items:center;gap:11px;margin-bottom:8px;min-width:0}
    .model-type-ic{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;flex:0 0 auto;overflow:hidden;box-shadow:inset 0 0 0 1px rgba(255,255,255,.18)}
    .model-type-ic svg{width:17px;height:17px}
    .model-type-ic.has-img{background:var(--panel);border:1px solid var(--line);padding:4px;box-shadow:var(--shadow-card)}
    .model-type-ic.has-img img{width:100%;height:100%;object-fit:contain;display:block;border-radius:8px}
    .t-chat{background:var(--brand-soft);color:var(--brand);border:1px solid var(--brand-line)}
    .t-embedding{background:var(--green-soft);color:var(--green-ink);border:1px solid var(--green-line)}
    .t-completion{background:var(--amber-soft);color:var(--amber-ink);border:1px solid var(--amber-line)}
    .t-other{background:var(--soft);color:var(--muted);border:1px solid var(--line)}
    .model-alias{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13.5px;font-weight:750;letter-spacing:-.01em;color:var(--ink);word-break:break-all;flex:1;min-width:0}
    .promo-sticker{position:absolute;top:9px;right:-7px;z-index:2;display:inline-flex;align-items:center;gap:4px;background:linear-gradient(135deg,#ef4444 0%,#f97316 100%);color:#fff;border-radius:9px 9px 9px 3px;padding:4px 10px;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;border:1px solid rgba(255,255,255,.4);box-shadow:0 4px 12px rgba(239,68,68,.45);transform:rotate(4deg);overflow:hidden;animation:stickerPop .28s cubic-bezier(.34,1.56,.64,1)}
    /* Kilap statis di bagian atas stiker (efek glossy) */
    .promo-sticker::before{content:"";position:absolute;inset:0 0 52% 0;background:linear-gradient(180deg,rgba(255,255,255,.5),rgba(255,255,255,0));border-radius:inherit;pointer-events:none}
    /* Sapuan cahaya animasi yang menyapu label secara berkala */
    .promo-sticker::after{content:"";position:absolute;top:-45%;bottom:-45%;left:-70%;width:42%;transform:rotate(22deg);background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.6),rgba(255,255,255,0));animation:stickerShine 3.2s ease-in-out 1.2s infinite;pointer-events:none}
    .promo-sticker svg{width:11px;height:11px;display:block;flex:0 0 auto;filter:drop-shadow(0 1px 1px rgba(0,0,0,.18))}
    @keyframes stickerPop{from{opacity:0;transform:rotate(4deg) scale(.6)}to{opacity:1;transform:rotate(4deg) scale(1)}}
    @keyframes stickerShine{0%,55%{left:-70%}85%,100%{left:135%}}
    .cap-pills{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px}
    .cap-pill{display:inline-flex;align-items:center;border-radius:999px;padding:2px 7px;font-size:10px;font-weight:700;letter-spacing:.02em;border:1px solid transparent}
    .cap-chat{background:var(--brand-soft);color:var(--brand);border-color:var(--brand-line)}
    .cap-completion{background:var(--amber-soft);color:var(--amber-ink);border-color:var(--amber-line)}
    .cap-embedding{background:var(--green-soft);color:var(--green-ink);border-color:var(--green-line)}
    .cap-tool{background:#ede9fe;color:#7c3aed;border-color:#ddd6fe}
    .cap-other{background:var(--soft);color:var(--muted);border-color:var(--line)}
    .cap-pill svg{width:10px;height:10px;display:block}
    .context-wrap{margin-top:10px}
    .context-top{display:flex;align-items:center;justify-content:space-between;font-size:11px}
    .context-top strong{font-variant-numeric:tabular-nums}
    .pricing{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:10px}
    .price-box{background:var(--soft);border:1px solid var(--line);border-radius:8px;padding:6px 9px}
    .price-box .lbl{display:block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px}
    .price-box .val{font-size:12px;font-weight:750;font-variant-numeric:tabular-nums}
    .price-box .original{display:block;color:var(--muted);font-size:10px;text-decoration:line-through;font-weight:600}
    .model-snippet{display:none;margin-top:10px}
    .model-snippet.open{display:block;animation:fadeUp .18s ease}
    @keyframes fadeUp{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
    .model-snippet pre{margin:0;background:#0f172a;border:1px solid #1e293b;color:#cbd5e1;border-radius:9px;padding:10px 12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11px;line-height:1.6;overflow-x:auto}
    .model-snippet pre .hl{color:#7dd3fc}
    .model-snippet pre .cm{color:#64748b}
    .model-foot{margin-top:auto;padding-top:10px;display:flex;gap:6px;flex-wrap:wrap}
    .btn.small{padding:5px 10px;font-size:12px;gap:5px}
    .btn.copied{background:var(--green);border-color:var(--green);color:#fff}
    .models-grid > .empty-state{grid-column:1/-1}
    .empty-state{text-align:center;padding:44px 24px}
    .empty-state .empty-ic{width:46px;height:46px;border-radius:13px;background:var(--soft);border:1px solid var(--line);color:var(--muted);display:grid;place-items:center;margin:0 auto 12px}
    .empty-state .empty-ic svg{width:20px;height:20px}
    .empty-state h3{margin:0 0 4px;font-size:15px;font-weight:750}
    .empty-state p{margin:0;color:var(--muted);font-size:13.5px}

    @media (min-width:640px) and (max-width:1099.98px){
        .models-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:639.98px){
        .models-grid{grid-template-columns:minmax(0,1fr)}
        .models-search{min-width:100%}
    }
</style>

<div class="top">
    <div><h2>{{ __('dashboard.titles.models') }}</h2><p>{{ __('dashboard.pages.models.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.pages.models.online', ['count' => $models->count()]) }}</span>
</div>

<div class="models-toolbar">
    <div class="models-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
        <input id="model-search" type="search" placeholder="{{ __('dashboard.pages.models.search_placeholder') }}" autocomplete="off" aria-label="{{ __('dashboard.pages.models.search_label') }}">
    </div>
    <div class="models-filters" role="group" aria-label="{{ __('dashboard.pages.models.capability_filter') }}">
        <button class="chip active" data-cap="all" aria-pressed="true">{{ __('dashboard.common.all') }} <span class="n">{{ $models->count() }}</span></button>
        @foreach($capabilityGroups as $cap => $count)
            <button class="chip" data-cap="{{ $cap }}" aria-pressed="false">{{ ucfirst($cap) }} <span class="n">{{ $count }}</span></button>
        @endforeach
        @php
            $promoCount = $models->filter(function ($model) {
                return (bool) ($model->latestPricingRule?->promo_is_active ?? false);
            })->count();
        @endphp
        @if($promoCount > 0)
            <button class="chip" data-cap="promo" aria-pressed="false">{{ __('dashboard.pages.models.promo') }} <span class="n">{{ $promoCount }}</span></button>
        @endif
    </div>
    <select id="model-sort" class="models-sort" aria-label="{{ __('dashboard.pages.models.sort_label') }}">
        <option value="name">{{ __('dashboard.pages.models.sort_name') }}</option>
        <option value="context">{{ __('dashboard.pages.models.sort_context') }}</option>
        <option value="price">{{ __('dashboard.pages.models.sort_price') }}</option>
    </select>
</div>

<div class="models-grid" id="models-grid">
    @forelse($models as $model)
        @php
            $type = strtolower($model->type ?? 'other');
            $typeClass = match ($type) {
                'chat' => 't-chat',
                'embedding' => 't-embedding',
                'completion' => 't-completion',
                default => 't-other',
            };
            $rule = $model->latestPricingRule;
            $inPrice = $rule ? format_idr_usd($rule->effective_input_price) : null;
            $outPrice = $rule ? format_idr_usd($rule->effective_output_price) : null;
            $originalInPrice = $rule?->original_input_per_million !== null ? format_idr_usd($rule->original_input_per_million) : null;
            $originalOutPrice = $rule?->original_output_per_million !== null ? format_idr_usd($rule->original_output_per_million) : null;
            $cacheReadPrice = $rule?->cache_read_input_per_million !== null ? format_idr_usd($rule->cache_read_input_per_million) : null;
            $cacheWritePrice = $rule?->cache_write_per_million !== null ? format_idr_usd($rule->cache_write_per_million) : null;
            $isPromo = (bool) ($rule?->promo_is_active ?? false);
            $caps = collect($model->capabilities ?: [$type])->map(fn ($c) => strtolower((string) $c))->filter(fn ($c) => $c !== '')->unique()->values();
            $capsAttr = $caps->implode(' ');
            $searchStr = strtolower(($model->public_name ?? '').' '.($model->provider?->name ?? 'default').' '.$capsAttr);
        @endphp
        <div class="model-card" data-type="{{ $type }}" data-caps="{{ $capsAttr }}" data-name="{{ strtolower($model->public_name ?? '') }}" data-context="{{ (int) ($model->context_window ?? 0) }}" data-price="{{ $rule ? (float) $rule->effective_input_price : 1e9 }}" data-promo="{{ $isPromo ? '1' : '0' }}" data-search="{{ $searchStr }}">
            <div class="model-head">
                <div class="model-type-ic {{ $model->icon_url ? 'has-img' : $typeClass }}">
                    @if($model->icon_url)
                        <img src="{{ $model->icon_url }}" alt="Ikon {{ $model->public_name }}" loading="lazy">
                    @elseif($type === 'embedding')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    @elseif($type === 'completion')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h10"/><path d="M4 18h7"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    @endif
                </div>
                <div class="model-alias" @if($isPromo) style="padding-right:78px" @endif title="{{ $model->public_name }}">{{ $model->public_name }}</div>
                @if($isPromo)<span class="promo-sticker" title="{{ __('dashboard.pages.models.promo_title') }}"><svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>{{ __('dashboard.pages.models.promo') }}</span>@endif
            </div>
            @if($caps->isNotEmpty())
            <div class="cap-pills">@foreach($caps as $cap)<span class="cap-pill cap-{{ $cap }}" title="{{ $cap }}">{!! capability_icon($cap) !!}</span>@endforeach</div>
            @endif
            @if($model->context_window)
            <div class="context-wrap">
                <div class="context-top"><span class="muted">{{ __('dashboard.pages.models.context_window') }}</span><strong title="{{ __('dashboard.landing.models.tokens', ['count' => number_format($model->context_window)]) }}">{{ format_compact_number($model->context_window) }}</strong></div>
            </div>
            @endif
            <div class="pricing">
                <div class="price-box"><span class="lbl">Input / 1M</span>@if($isPromo && $originalInPrice)<span class="original">{{ $originalInPrice }}</span>@endif<span class="val">{{ $inPrice ?? '—' }}</span></div>
                <div class="price-box"><span class="lbl">Output / 1M</span>@if($isPromo && $originalOutPrice)<span class="original">{{ $originalOutPrice }}</span>@endif<span class="val">{{ $outPrice ?? '—' }}</span></div>
                <div class="price-box"><span class="lbl" title="Harga input saat token dilayani dari cache provider">Cache Read / 1M</span><span class="val">{{ $cacheReadPrice ?? '—' }}</span></div>
                <div class="price-box"><span class="lbl" title="Harga per token yang ditulis ke cache">Cache Write / 1M</span><span class="val">{{ $cacheWritePrice ?? '—' }}</span></div>
            </div>
            <div class="model-snippet" id="snippet-{{ $model->id }}">
                @if($type === 'embedding')
                    <pre><span class="cm"># Python + OpenAI SDK</span>
<span class="hl">from openai import OpenAI</span>

client = OpenAI(
    base_url=<span class="hl">"https://api.azkia.cloud/v1"</span>,
    api_key=<span class="hl">"azkia_xxxxx"</span>,
)

response = client.embeddings.create(
    model=<span class="hl">"{{ $model->public_name }}"</span>,
    input=<span class="hl">"Teks yang ingin di-embed"</span>,
)</pre>
                @else
                    <pre><span class="cm"># Python + OpenAI SDK</span>
<span class="hl">from openai import OpenAI</span>

client = OpenAI(
    base_url=<span class="hl">"https://api.azkia.cloud/v1"</span>,
    api_key=<span class="hl">"azkia_xxxxx"</span>,
)

response = client.chat.completions.create(
    model=<span class="hl">"{{ $model->public_name }}"</span>,
    messages=[{"role": "user", "content": "Halo!"}],
)</pre>
                @endif
            </div>
            <div class="model-foot">
                <button class="btn small" data-copy="{{ $model->public_name }}">{{ __('dashboard.pages.models.copy_alias') }}</button>
                <button class="btn secondary small" data-toggle-snippet aria-expanded="false" aria-controls="snippet-{{ $model->id }}">{{ __('dashboard.pages.models.show_example') }}</button>
            </div>
        </div>
    @empty
        <div class="card empty-state">
            <div class="empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3"/><path d="M12 18v3"/><path d="M3 12h3"/><path d="M18 12h3"/><path d="M5.6 5.6l2.1 2.1"/><path d="M16.3 16.3l2.1 2.1"/><path d="M18.4 5.6l-2.1 2.1"/><path d="M7.7 16.3l-2.1 2.1"/></svg></div>
            <h3>{{ __('dashboard.pages.models.empty') }}</h3>
            <p>{{ __('dashboard.pages.models.empty_hint') }}</p>
        </div>
    @endforelse
</div>

<div class="card empty-state" id="models-empty" style="display:none">
    <div class="empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg></div>
    <h3>{{ __('dashboard.pages.models.no_results') }}</h3>
    <p>{{ __('dashboard.pages.models.no_results_hint') }}</p>
</div>

<script>
(function () {
    var input = document.getElementById('model-search');
    var sortSel = document.getElementById('model-sort');
    var grid = document.getElementById('models-grid');
    var empty = document.getElementById('models-empty');
    var cards = Array.prototype.slice.call(document.querySelectorAll('.model-card'));
    var capChips = Array.prototype.slice.call(document.querySelectorAll('.chip[data-cap]'));
    var activeCap = 'all';
    var sortBy = 'name';

    function matches(card) {
        var q = (input ? input.value : '').trim().toLowerCase();
        var okCap = activeCap === 'all'
            || (activeCap === 'promo' && card.getAttribute('data-promo') === '1')
            || (card.getAttribute('data-caps') || '').split(' ').indexOf(activeCap) !== -1;
        var okQ = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
        return okCap && okQ;
    }

    function comparator(a, b) {
        if (sortBy === 'context') {
            return (parseInt(b.getAttribute('data-context')) || 0) - (parseInt(a.getAttribute('data-context')) || 0);
        }
        if (sortBy === 'price') {
            return (parseFloat(a.getAttribute('data-price')) || 0) - (parseFloat(b.getAttribute('data-price')) || 0);
        }
        return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
    }

    function applyFilter() {
        var visible = 0;
        var visibleCards = [];
        cards.forEach(function (card) {
            var show = matches(card);
            card.style.display = show ? '' : 'none';
            if (show) { visible++; visibleCards.push(card); }
        });
        visibleCards.sort(comparator);
        visibleCards.forEach(function (card) { grid.appendChild(card); });
        if (empty && cards.length > 0) empty.style.display = visible === 0 ? '' : 'none';
    }

    function wireChips(chips, attr, setter) {
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
                chip.classList.add('active');
                chip.setAttribute('aria-pressed', 'true');
                setter(chip.getAttribute(attr));
                applyFilter();
            });
        });
    }

    wireChips(capChips, 'data-cap', function (v) { activeCap = v; });

    if (input) input.addEventListener('input', applyFilter);
    if (sortSel) sortSel.addEventListener('change', function () { sortBy = sortSel.value; applyFilter(); });

    document.addEventListener('click', function (e) {
        if (!(e.target instanceof Element)) return;
        var snippetBtn = e.target.closest('[data-toggle-snippet]');
        if (snippetBtn) {
            var card = snippetBtn.closest('.model-card');
            var snip = card.querySelector('.model-snippet');
            var open = snip.classList.toggle('open');
            snippetBtn.textContent = open ? @json(__('dashboard.pages.models.hide_example')) : @json(__('dashboard.pages.models.show_example'));
            snippetBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        var copyBtn = e.target.closest('[data-copy]');
        if (copyBtn) {
            var text = copyBtn.getAttribute('data-copy');
            function flashCopied() {
                var orig = copyBtn.textContent;
                copyBtn.classList.add('copied');
                copyBtn.textContent = @json(__('dashboard.common.copied')) + ' ✓';
                setTimeout(function () {
                    copyBtn.classList.remove('copied');
                    copyBtn.textContent = orig;
                }, 1300);
            }
            function fallback() {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (err) {}
                document.body.removeChild(ta);
                flashCopied();
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(flashCopied).catch(fallback);
            } else {
                fallback();
            }
        }
    });
})();
</script>
@endsection

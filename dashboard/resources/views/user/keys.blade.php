@extends('layouts.app')

@section('content')
<style>
    /* ===== Halaman API Keys ===== */
    .keys-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
    .stat{display:flex;align-items:center;gap:12px;padding:16px 18px}
    .stat-ic{width:38px;height:38px;border-radius:11px;background:var(--brand-soft);border:1px solid var(--brand-line);color:var(--brand);display:grid;place-items:center;flex:0 0 auto}
    .stat-ic svg{width:18px;height:18px}
    .stat-ic.ic-green{background:var(--green-soft);border-color:var(--green-line);color:var(--green-ink)}
    .stat-ic.ic-amber{background:var(--amber-soft);border-color:var(--amber-line);color:var(--amber-ink)}
    .stat-ic.ic-slate{background:var(--soft);border-color:var(--line);color:var(--muted)}
    .stat-lbl{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)}
    .stat .metric{margin-top:2px}
    .expiry-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;margin:14px 0 5px}
    .expiry-toggle input[type="checkbox"]{width:16px;height:16px;margin:0;padding:0;accent-color:var(--brand);flex:0 0 auto}

    .created-key{border-color:var(--green-line);background:linear-gradient(180deg,#fbfefc,var(--panel))}
    .created-key h3{color:var(--green-ink)}
    .key-row{display:flex;align-items:flex-start;gap:10px}
    .key-row .key{flex:1}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
    .usage-cell{font-size:12.5px;line-height:1.5}
    .usage-cell strong{font-variant-numeric:tabular-nums}

    .btn.small{padding:6px 12px;font-size:12.5px;gap:6px}
    .btn.copied{background:var(--green);border-color:var(--green);color:#fff}

    .table-empty{text-align:center;padding:36px 20px;color:var(--muted)}
    .table-empty h3{margin:0 0 4px;font-size:15px;font-weight:750;color:var(--ink)}
    .table-empty p{margin:0;font-size:13.5px}

    .modal-backdrop{position:fixed;inset:0;z-index:60;background:rgba(15,23,42,.5);backdrop-filter:blur(2px);display:none;align-items:center;justify-content:center;padding:20px}
    .modal-backdrop.open{display:flex}
    .modal{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(15,23,42,.25);animation:fadeUp .18s ease}
    @keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
    .modal h3{margin:0 0 8px;font-size:16px;font-weight:800;letter-spacing:-.01em}
    .modal p{margin:0 0 20px;color:var(--muted);font-size:13.5px}
    .modal p strong{color:var(--ink)}
    .modal-actions{display:flex;justify-content:flex-end;gap:10px}

    @media (min-width:640px) and (max-width:1099.98px){
        .keys-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:639.98px){
        .keys-stats{grid-template-columns:minmax(0,1fr)}
        .key-row{flex-direction:column}
    }
</style>

<div class="top">
    <div><h2>{{ __('dashboard.titles.keys') }}</h2><p>{{ __('dashboard.pages.keys.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.pages.keys.bearer_auth') }}</span>
</div>

@if(session('created_api_key'))
<div class="section card created-key">
    <h3>{{ __('dashboard.pages.keys.new_secret') }} ✓</h3>
    <p class="muted">{{ __('dashboard.pages.keys.secret_once') }}</p>
    <div class="key-row">
        <div class="key">{{ session('created_api_key') }}</div>
        <button class="btn small" data-copy="{{ session('created_api_key') }}">{{ __('dashboard.common.copy') }}</button>
    </div>
</div>
@endif

<div class="keys-stats">
    <div class="card stat">
        <div class="stat-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><path d="M10.5 12.5 20 3"/><path d="M15.5 7.5 18.5 10.5"/><path d="M18 4.5 20 6.5"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.pages.keys.total_keys') }}</div><div class="metric small">{{ $apiKeys->count() }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.pages.keys.active_keys') }}</div><div class="metric small">{{ $activeKeys }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20v-6"/><path d="M12 20V8"/><path d="M18 20v-10"/><path d="M3 20h18"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.overview.total_requests') }}</div><div class="metric small">{{ number_format($totalRequests) }}</div></div>
    </div>
    <div class="card stat">
        <div class="stat-ic ic-slate"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/><path d="M6 15h4"/></svg></div>
        <div><div class="stat-lbl">{{ __('dashboard.overview.total_cost') }}</div><div class="metric small">{{ format_idr_usd($totalCost) }}</div></div>
    </div>
</div>

<div class="grid2">
    <div class="card">
        <h3>{{ __('dashboard.pages.keys.create') }}</h3>
        <form method="post" action="{{ route('api-keys.store') }}">@csrf
            <label class="muted">{{ __('dashboard.common.name') }}</label>
            <input name="name" placeholder="Production App" required maxlength="255">
            <label class="muted">{{ __('dashboard.pages.keys.monthly_quota') }}</label>
            <input name="monthly_quota_tokens" type="number" min="1" placeholder="{{ __('dashboard.common.optional') }}">
            <label class="muted expiry-toggle">
                <input type="checkbox" name="no_expiry" id="no-expiry" checked>
                {{ __('dashboard.pages.keys.no_expiry') }}
            </label>
            <label class="muted" id="expiry-date-label" style="display:none">{{ __('dashboard.pages.keys.expires_at') }}</label>
            <input name="expires_at" id="expiry-date" type="date" min="{{ date('Y-m-d') }}" disabled>
            <button>{{ __('dashboard.pages.keys.create_button') }}</button>
        </form>
    </div>
    <div class="card">
        <h3>{{ __('dashboard.pages.keys.security') }}</h3>
        <p class="muted">{{ __('dashboard.pages.keys.security_text') }}</p>
        <div class="key-row">
            <div class="key">Authorization: Bearer azkia_xxxxx</div>
            <button class="btn small secondary" data-copy="Authorization: Bearer azkia_xxxxx">{{ __('dashboard.common.copy') }}</button>
        </div>
    </div>
</div>

<div class="section card">
    <h3>{{ __('dashboard.pages.keys.your_keys') }}</h3>
    @if($apiKeys->isEmpty())
        <div class="table-empty">
            <h3>{{ __('dashboard.pages.keys.empty') }}</h3>
            <p>{{ __('dashboard.pages.keys.empty_hint') }}</p>
        </div>
    @else
        <div class="table-wrap"><table>
            <tr><th>{{ __('dashboard.common.name') }}</th><th>{{ __('dashboard.overview.prefix') }}</th><th>{{ __('dashboard.pages.keys.quota') }}</th><th>{{ __('dashboard.pages.keys.expires') }}</th><th>{{ __('dashboard.pages.keys.usage') }}</th><th>{{ __('dashboard.common.status') }}</th><th>{{ __('dashboard.pages.keys.last_used') }}</th><th>{{ __('dashboard.common.actions') }}</th></tr>
            @foreach($apiKeys as $key)
                @php($stat = $usageStats->get($key->id))
                <tr>
                    <td><strong>{{ $key->name }}</strong></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <span class="mono">{{ $key->prefix }}…</span>
                            <button class="btn small secondary" data-copy="{{ $key->prefix }}">{{ __('dashboard.common.copy') }}</button>
                        </div>
                    </td>
                    <td>{{ $key->monthly_quota_tokens ? format_compact_number($key->monthly_quota_tokens).' tok' : __('dashboard.common.unlimited') }}</td>
                    <td>
                        @if($key->expires_at)
                            @if($key->expires_at->isPast())
                                <span class="badge red">{{ __('dashboard.pages.keys.expired') }}</span>
                            @elseif($key->expires_at->lt(now()->addDays(7)))
                                <span class="badge amber">{{ __('dashboard.pages.keys.soon') }}</span>
                            @endif
                            <span class="muted">{{ $key->expires_at->locale(app()->getLocale())->translatedFormat('d M Y') }}</span>
                        @else
                            <span class="badge green">{{ __('dashboard.common.unlimited') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($stat)
                            <div class="usage-cell"><strong>{{ number_format($stat->requests) }}</strong> req<br><span class="muted">{{ format_compact_number($stat->tokens) }} tok · {{ format_idr_usd($stat->cost) }}</span></div>
                        @else
                            <span class="muted">{{ __('dashboard.pages.keys.unused') }}</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $key->is_active ? 'green' : 'red' }}">{{ $key->is_active ? __('dashboard.common.active') : __('dashboard.common.inactive') }}</span></td>
                    <td>{{ $key->last_used_at?->locale(app()->getLocale())->diffForHumans() ?? '-' }}</td>
                    <td>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <form method="post" action="{{ route('api-keys.toggle', $key) }}">@csrf @method('patch')
                                <button class="btn small secondary">{{ $key->is_active ? __('dashboard.common.disable') : __('dashboard.common.enable') }}</button>
                            </form>
                            @if($key->expires_at)
                                <form method="post" action="{{ route('api-keys.no-expiry', $key) }}">@csrf @method('patch')
                                    <button class="btn small secondary" title="{{ __('dashboard.pages.keys.remove_expiry') }}">{{ __('dashboard.common.unlimited') }}</button>
                                </form>
                            @endif
                            <button class="btn small danger" data-confirm-delete data-action="{{ route('api-keys.destroy', $key) }}" data-name="{{ $key->name }}">{{ __('dashboard.common.delete') }}</button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </table></div>
    @endif
</div>

<div class="modal-backdrop" id="confirm-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <h3 id="confirm-title">{{ __('dashboard.pages.keys.delete_title') }}</h3>
        <p>{!! __('dashboard.pages.keys.delete_text', ['name' => '<strong id="confirm-key-name"></strong>']) !!}</p>
        <div class="modal-actions">
            <button class="btn secondary" data-close-modal>{{ __('dashboard.common.cancel') }}</button>
            <form id="confirm-form" method="post" action="">@csrf @method('delete')<button class="btn danger">{{ __('dashboard.common.yes_delete') }}</button></form>
        </div>
    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('confirm-modal');
    var confirmForm = document.getElementById('confirm-form');
    var keyName = document.getElementById('confirm-key-name');
    var closeBtn = backdrop.querySelector('[data-close-modal]');
    var lastTrigger = null;
    var thisKey = @json(__('dashboard.pages.keys.this_key'));
    var copiedLabel = @json(__('dashboard.common.copied'));

    var noExpiry = document.getElementById('no-expiry');
    var expiryDate = document.getElementById('expiry-date');
    var expiryLabel = document.getElementById('expiry-date-label');
    function syncExpiry() {
        var off = noExpiry.checked;
        expiryDate.disabled = off;
        expiryDate.style.display = off ? 'none' : '';
        expiryLabel.style.display = off ? 'none' : '';
    }
    if (noExpiry) {
        noExpiry.addEventListener('change', syncExpiry);
        syncExpiry();
    }

    function openModal(trigger) {
        lastTrigger = trigger;
        backdrop.classList.add('open');
        backdrop.setAttribute('aria-hidden', 'false');
        closeBtn.focus();
    }
    function closeModal() {
        backdrop.classList.remove('open');
        backdrop.setAttribute('aria-hidden', 'true');
        if (lastTrigger) lastTrigger.focus();
    }

    document.addEventListener('click', function (e) {
        if (!(e.target instanceof Element)) return;

        var del = e.target.closest('[data-confirm-delete]');
        if (del) {
            confirmForm.setAttribute('action', del.getAttribute('data-action'));
            keyName.textContent = del.getAttribute('data-name') || thisKey;
            openModal(del);
            return;
        }

        var copyBtn = e.target.closest('[data-copy]');
        if (copyBtn) {
            var text = copyBtn.getAttribute('data-copy');
            function flashCopied() {
                var orig = copyBtn.textContent;
                copyBtn.classList.add('copied');
                copyBtn.textContent = copiedLabel + ' ✓';
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

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
})();
</script>
@endsection

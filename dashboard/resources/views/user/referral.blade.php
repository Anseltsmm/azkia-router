@extends('layouts.app')

@section('content')
<style>
    .ref-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .ref-card .card{padding:22px}
    .ref-link{display:flex;align-items:center;gap:10px;margin-top:4px}
    .ref-link .key{flex:1;margin:0}
    .ref-link button{flex:0 0 auto}
    .ref-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:4px}
    .ref-step{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:14px}
    .ref-step .n{width:22px;height:22px;border-radius:7px;background:var(--brand);color:#fff;display:grid;place-items:center;font-size:11px;font-weight:800;margin-bottom:8px}
    .ref-step p{margin:0;font-size:12.5px;color:var(--body);line-height:1.5}

    @media (max-width:899.98px){
        .ref-grid{grid-template-columns:minmax(0,1fr)}
        .ref-steps{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:639.98px){
        .ref-steps{grid-template-columns:minmax(0,1fr)}
        .ref-link{flex-direction:column;align-items:stretch}
        .ref-link button{width:100%}
    }
</style>

<div class="top">
    <div><h2>{{ __('dashboard.pages.referral.heading') }}</h2><p>{{ __('dashboard.pages.referral.subtitle') }}</p></div>
    <span class="pill">{{ __('dashboard.common.account') }}</span>
</div>

<div class="ref-card">
    <div class="card">
        <h3>{{ __('dashboard.pages.referral.your_link') }}</h3>
        <p class="muted" style="margin:0 0 6px">{{ __('dashboard.pages.referral.share_hint', ['reward' => $rewardText, 'amount' => $minTopupText]) }}</p>
        <div class="ref-link">
            <div class="key" style="padding:11px 14px">{{ $referralLink }}</div>
            <button id="ref-copy" type="button">{{ __('dashboard.pages.referral.copy') }}</button>
        </div>
    </div>
</div>

<div class="ref-grid section">
    <div class="card"><h3>{{ __('dashboard.pages.referral.stats_total') }}</h3><div class="metric small">{{ $totalReferrals }}</div></div>
    <div class="card"><h3>{{ __('dashboard.pages.referral.stats_earned') }}</h3><div class="metric small">${{ number_format((float) $totalEarned, 2) }}</div></div>
    <div class="card"><h3>{{ __('dashboard.pages.referral.stats_pending') }}</h3><div class="metric small">{{ $pendingReferrals }}</div></div>
</div>

<div class="section">
    <h3 style="margin:0 0 10px;font-size:14px;font-weight:750">{{ __('dashboard.pages.referral.how_title') }}</h3>
    <div class="ref-steps">
        <div class="ref-step"><div class="n">1</div><p>{{ __('dashboard.pages.referral.step_1') }}</p></div>
        <div class="ref-step"><div class="n">2</div><p>{{ __('dashboard.pages.referral.step_2') }}</p></div>
        <div class="ref-step"><div class="n">3</div><p>{{ __('dashboard.pages.referral.step_3', ['amount' => $minTopupText]) }}</p></div>
        <div class="ref-step"><div class="n">4</div><p>{{ __('dashboard.pages.referral.step_4', ['reward' => $rewardText]) }}</p></div>
    </div>
    <p class="muted" style="font-size:12.5px;margin:12px 2px 0">{{ __('dashboard.pages.referral.reward_note') }}</p>
</div>

<script>
    (function () {
        var btn = document.getElementById('ref-copy');
        if (!btn) return;
        var link = @json($referralLink);
        btn.addEventListener('click', function () {
            var done = function () {
                btn.textContent = @json(__('dashboard.pages.referral.copied'));
                setTimeout(function () { btn.textContent = @json(__('dashboard.pages.referral.copy')); }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(link).then(done, done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = link;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                done();
            }
        });
    })();
</script>
@endsection

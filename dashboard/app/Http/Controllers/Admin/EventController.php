<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\FinancialAuditEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TopupPromoService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index()
    {
        return view('admin.event', [
            'enabled' => (bool) filter_var(AppSetting::get('referral.enabled', '1'), FILTER_VALIDATE_BOOL),
            'rewardUsd' => AppSetting::get('referral.reward_usd', config('referral.reward_usd')),
            'minTopupIdr' => AppSetting::get('referral.min_topup_idr', config('referral.min_topup_idr')),
            'totalReferrals' => User::whereNotNull('referred_by')->count(),
            'pendingReferrals' => User::whereNotNull('referred_by')->whereNull('referral_rewarded_at')->count(),
            'rewardsPaid' => Transaction::where('type', 'referral_reward')->count(),
            'rewardsTotalUsd' => (string) Transaction::where('type', 'referral_reward')->sum('amount'),
            'topupPromo' => app(TopupPromoService::class),
        ]);
    }

    public function updateTopup(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'type' => ['required', 'in:tier,percent'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tiers' => ['nullable', 'array', 'max:20'],
            'tiers.*.min_idr' => ['nullable', 'integer', 'min:1000', 'max:10000000'],
            'tiers.*.bonus_idr' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $enabled = $request->boolean('enabled');
        $type = $data['type'];
        $before = [
            'enabled' => AppSetting::get('topup_promo.enabled', '0'),
            'type' => AppSetting::get('topup_promo.type', 'tier'),
            'percent' => AppSetting::get('topup_promo.percent', '0'),
            'tiers' => AppSetting::get('topup_promo.tiers', '[]'),
        ];

        // Baris tier yang tidak lengkap (min/bonus kosong) diabaikan.
        $tiers = collect($data['tiers'] ?? [])
            ->filter(fn ($t) => isset($t['min_idr'], $t['bonus_idr']) && (int) $t['min_idr'] > 0 && (int) $t['bonus_idr'] > 0)
            ->map(fn ($t) => ['min_idr' => (int) $t['min_idr'], 'bonus_idr' => (int) $t['bonus_idr']])
            ->values()
            ->all();

        AppSetting::set('topup_promo.enabled', $enabled ? '1' : '0');
        AppSetting::set('topup_promo.type', $type);
        AppSetting::set('topup_promo.percent', number_format((float) ($data['percent'] ?? 0), 2, '.', ''));
        AppSetting::set('topup_promo.tiers', json_encode($tiers));

        FinancialAuditEvent::create([
            'actor_id' => $request->user()->id,
            'action' => 'event_topup_settings',
            'metadata' => [
                'before' => $before,
                'after' => [
                    'enabled' => $enabled ? '1' : '0',
                    'type' => $type,
                    'percent' => number_format((float) ($data['percent'] ?? 0), 2, '.', ''),
                    'tiers' => json_encode($tiers),
                ],
            ],
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        return back()->with('success', 'Pengaturan event top up disimpan.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'reward_usd' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_topup_idr' => ['required', 'integer', 'min:1000', 'max:10000000'],
        ]);

        $before = [
            'enabled' => AppSetting::get('referral.enabled', '1'),
            'reward_usd' => AppSetting::get('referral.reward_usd', config('referral.reward_usd')),
            'min_topup_idr' => AppSetting::get('referral.min_topup_idr', config('referral.min_topup_idr')),
        ];

        AppSetting::set('referral.enabled', $request->boolean('enabled') ? '1' : '0');
        AppSetting::set('referral.reward_usd', number_format((float) $data['reward_usd'], 6, '.', ''));
        AppSetting::set('referral.min_topup_idr', (string) $data['min_topup_idr']);

        // Audit trail: perubahan pengaturan program referral dicatat siapa yang mengubah.
        FinancialAuditEvent::create([
            'actor_id' => $request->user()->id,
            'action' => 'event_referral_settings',
            'metadata' => [
                'before' => $before,
                'after' => [
                    'enabled' => $request->boolean('enabled') ? '1' : '0',
                    'reward_usd' => number_format((float) $data['reward_usd'], 6, '.', ''),
                    'min_topup_idr' => (string) $data['min_topup_idr'],
                ],
            ],
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);

        return back()->with('success', 'Pengaturan program referral disimpan.');
    }
}

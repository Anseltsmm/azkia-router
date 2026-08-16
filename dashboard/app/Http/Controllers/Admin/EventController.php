<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\FinancialAuditEvent;
use App\Models\Transaction;
use App\Models\User;
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
        ]);
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

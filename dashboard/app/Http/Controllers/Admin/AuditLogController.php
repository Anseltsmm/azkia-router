<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialAuditEvent;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Log audit keuangan & pengaturan — semua action tercatat di
     * financial_audit_events (manual_credit, topup_bonus, referral_reward,
     * event_topup_settings, event_referral_settings, deposit_*, redeem_credit).
     */
    public function index(Request $request)
    {
        $query = FinancialAuditEvent::with(['actor', 'targetUser', 'paymentOrder']);

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('actor', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('targetUser', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $events = $query->latest()->paginate(50)->withQueryString();
        $actions = FinancialAuditEvent::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit-log', [
            'events' => $events,
            'actions' => $actions,
            'stats' => [
                'total' => FinancialAuditEvent::count(),
                'topupBonus' => FinancialAuditEvent::where('action', 'topup_bonus')->count(),
                'referralReward' => FinancialAuditEvent::where('action', 'referral_reward')->count(),
                'settings' => FinancialAuditEvent::whereIn('action', ['event_topup_settings', 'event_referral_settings'])->count(),
            ],
        ]);
    }
}

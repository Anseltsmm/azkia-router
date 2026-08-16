<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingMonitoringController extends Controller
{
    private const ACTIVE = ['pending', 'reserved'];

    public function index(Request $request)
    {
        $query = BillingEvent::query()->with(['user', 'apiKey']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('model')) {
            $query->where('model', $request->string('model'));
        }
        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(fn (Builder $q) => $q->whereRaw('cast(id as text) like ?', ["%{$term}%"])->orWhere('upstream_request_id', 'like', "%{$term}%")->orWhereHas('user', fn (Builder $u) => $u->where('email', 'like', "%{$term}%")));
        }
        if ($request->filled('age')) {
            $minutes = min(10080, max(1, $request->integer('age')));
            $query->where('created_at', '<=', now()->subMinutes($minutes));
        }
        $this->applyRetryFilter($query, $request->input('retry'));
        $active = BillingEvent::whereIn('status', self::ACTIVE);
        $recent = fn (string $status) => BillingEvent::where('status', $status)->where('updated_at', '>=', now()->subDay())->count();
        $eventReserved = BillingEvent::whereIn('status', self::ACTIVE)->selectRaw('user_id, sum(reserved_cost) total')->groupBy('user_id');
        $discrepancy = User::query()->leftJoinSub($eventReserved, 'event_reserves', 'event_reserves.user_id', '=', 'users.id')->whereRaw('abs(coalesce(users.reserved_balance, 0) - coalesce(event_reserves.total, 0)) > 0.000001');

        return view('admin.billing-monitoring.index', [
            'events' => $query->latest()->paginate(30)->withQueryString(),
            'models' => BillingEvent::whereNotNull('model')->distinct()->orderBy('model')->pluck('model'),
            'stats' => [
                'active_count' => (clone $active)->count(), 'active_cost' => (clone $active)->sum('reserved_cost'), 'active_tokens' => (clone $active)->sum('reserved_tokens'),
                'aged' => (clone $active)->where('created_at', '<=', now()->subMinutes(15))->count(),
                'retry_due' => (clone $active)->whereNotNull('next_retry_at')->where('next_retry_at', '<=', now())->count(),
                'retry_exhausted' => (clone $active)->where(fn (Builder $q) => $q->where('retry_count', '>=', 12)->orWhere(fn (Builder $e) => $e->whereNotNull('last_error')->whereNull('next_retry_at')))->count(),
                'settled' => $recent('settled'), 'partial' => $recent('partially_settled'), 'released' => $recent('released'), 'failed' => $recent('failed'),
                'discrepancy_count' => (clone $discrepancy)->count(),
                'discrepancy_value' => (clone $discrepancy)->sum(DB::raw('abs(coalesce(users.reserved_balance, 0) - coalesce(event_reserves.total, 0))')),
            ],
        ]);
    }

    public function show(BillingEvent $billingEvent)
    {
        $billingEvent->load(['user', 'apiKey', 'usageLog', 'transaction']);
        $activeReserved = BillingEvent::where('user_id', $billingEvent->user_id)->whereIn('status', self::ACTIVE)->sum('reserved_cost');

        return view('admin.billing-monitoring.show', ['event' => $billingEvent, 'payload' => $this->sanitizedPayload($billingEvent->payload ?? []), 'activeReserved' => $activeReserved]);
    }

    private function applyRetryFilter(Builder $query, ?string $state): void
    {
        if ($state === 'due') {
            $query->whereIn('status', self::ACTIVE)->whereNotNull('next_retry_at')->where('next_retry_at', '<=', now());
        }
        if ($state === 'scheduled') {
            $query->where('next_retry_at', '>', now());
        }
        if ($state === 'exhausted') {
            $query->whereIn('status', self::ACTIVE)->where(fn (Builder $q) => $q->where('retry_count', '>=', 12)->orWhere(fn (Builder $e) => $e->whereNotNull('last_error')->whereNull('next_retry_at')));
        }
    }

    private function sanitizedPayload(array $payload): array
    {
        $allowed = ['model', 'endpoint', 'status', 'status_code', 'input_tokens', 'output_tokens', 'total_tokens', 'cost', 'reserved_cost', 'reserved_tokens', 'usage_source', 'usage_quality', 'settlement_kind', 'upstream_request_id', 'latency_ms', 'retry_count', 'error_code', 'finish_reason'];

        return collect($payload)->only($allowed)->map(fn ($value) => is_scalar($value) || $value === null ? $value : '[redacted]')->all();
    }
}

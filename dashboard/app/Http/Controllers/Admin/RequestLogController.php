<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UsageLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RequestLogController extends Controller
{
    public function index(Request $request)
    {
        $query = UsageLog::query()->with(['user', 'apiKey']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('model')) {
            $query->where('model', $request->string('model'));
        }
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $status === 'error' ? $query->where('status_code', '>=', 400) : $query->where('status_code', '<', 400);
        }
        if ($request->filled('source')) {
            $query->where('usage_source', $request->string('source'));
        }
        if ($request->filled('quality')) {
            $query->where('usage_quality', $request->string('quality'));
        }
        if ($request->filled('cache')) {
            match ($request->string('cache')->toString()) {
                'read' => $query->where('cache_read', true),
                'write' => $query->where('cache_write', true),
                'none' => $query->where('cache_read', false)->where('cache_write', false),
                default => null,
            };
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }
        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(fn (Builder $q) => $q
                ->where('request_id', 'ilike', "%{$term}%")
                ->orWhere('upstream_request_id', 'ilike', "%{$term}%")
                ->orWhere('endpoint', 'ilike', "%{$term}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'ilike', "%{$term}%"))
                ->orWhereHas('apiKey', fn (Builder $k) => $k->where('name', 'ilike', "%{$term}%")->orWhere('prefix', 'ilike', "%{$term}%")));
        }

        $today = $this->periodStats(now()->startOfDay(), now());
        $yesterday = $this->periodStats(now()->subDay()->startOfDay(), now()->subDay()->endOfDay());
        $hourlyRows = UsageLog::query()
            ->where('created_at', '>=', now()->subDay()->startOfDay())
            ->selectRaw("date_trunc('hour', created_at) as hour, count(*) requests, count(*) filter (where status_code >= 400) errors, sum(input_tokens + output_tokens) tokens")
            ->groupByRaw("date_trunc('hour', created_at)")
            ->orderBy('hour')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->hour)->format('Y-m-d H'));
        $hourly = collect(range(0, 23))->map(function (int $hour) use ($hourlyRows) {
            $todayHour = now()->startOfDay()->addHours($hour);
            $yesterdayHour = now()->subDay()->startOfDay()->addHours($hour);
            $current = $hourlyRows->get($todayHour->format('Y-m-d H'));
            $previous = $hourlyRows->get($yesterdayHour->format('Y-m-d H'));

            return [
                'label' => $todayHour->format('H:i'),
                'today' => (int) ($current->requests ?? 0),
                'yesterday' => (int) ($previous->requests ?? 0),
                'errors' => (int) ($current->errors ?? 0),
                'tokens' => (int) ($current->tokens ?? 0),
            ];
        });
        $topModels = UsageLog::query()->where('created_at', '>=', now()->startOfDay())->select('model')->selectRaw('count(*) requests, sum(input_tokens + output_tokens) tokens, sum(cost) cost, count(*) filter (where status_code >= 400) errors')->groupBy('model')->orderByDesc('requests')->limit(6)->get();
        $topUsers = UsageLog::query()->leftJoin('users', 'users.id', '=', 'usage_logs.user_id')->where('usage_logs.created_at', '>=', now()->startOfDay())->select('usage_logs.user_id', 'users.email')->selectRaw('count(*) requests, sum(usage_logs.input_tokens + usage_logs.output_tokens) tokens, sum(usage_logs.cost) cost')->groupBy('usage_logs.user_id', 'users.email')->orderByDesc('requests')->limit(6)->get();
        $breakdown = UsageLog::query()->where('created_at', '>=', now()->startOfDay())->selectRaw("count(*) filter (where usage_quality = 'reported') reported, count(*) filter (where usage_quality = 'estimated') estimated, count(*) filter (where cache_read = true) cache_hits, count(*) filter (where cache_write = true) cache_writes, count(*) filter (where status_code < 400) successful, count(*) filter (where status_code >= 400) failed")->first();

        return view('admin.request-logs.index', [
            'logs' => $query->latest()->paginate(30)->withQueryString(),
            'users' => User::orderBy('email')->get(['id', 'email']),
            'models' => UsageLog::distinct()->orderBy('model')->pluck('model'),
            'sources' => UsageLog::distinct()->orderBy('usage_source')->pluck('usage_source'),
            'today' => $today,
            'yesterday' => $yesterday,
            'hourly' => $hourly,
            'topModels' => $topModels,
            'topUsers' => $topUsers,
            'breakdown' => $breakdown,
        ]);
    }

    public function show(UsageLog $usageLog)
    {
        $usageLog->load(['user', 'apiKey', 'billingEvent']);

        return view('admin.request-logs.show', ['log' => $usageLog]);
    }

    private function periodStats($start, $end): object
    {
        return UsageLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('count(*) requests, count(*) filter (where status_code >= 400) errors, coalesce(sum(input_tokens), 0) input_tokens, coalesce(sum(output_tokens), 0) output_tokens, coalesce(sum(cost), 0) cost, coalesce(avg(latency_ms), 0) avg_latency, count(*) filter (where cache_read = true) cache_hits')
            ->first();
    }
}

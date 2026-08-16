<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\ApiKey;
use App\Models\InboxMessage;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Transaction;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\UserPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index()
    {
        if (! Auth::check()) {
            return view('welcome', [
                'models' => AiModel::with('provider', 'latestPricingRule')
                    ->where('is_active', true)
                    ->orderBy('public_name')
                    ->get(),
                // Plan berbayar yang dijual (termurah dulu) untuk section harga landing page.
                'plans' => Plan::where('is_active', true)
                    ->where('resets_daily', false)
                    ->with('models')
                    ->orderBy('price_usd')
                    ->get(),
                // Plan gratis (reset harian) ditampilkan sebagai kartu "Gratis".
                'freePlan' => Plan::where('is_active', true)->where('resets_daily', true)->first(),
            ]);
        }

        $user = Auth::user();
        $usage = UsageLog::where('user_id', $user->id);
        $today = $this->overviewPeriodStats($user->id, now()->startOfDay(), now());
        $yesterday = $this->overviewPeriodStats($user->id, now()->subDay()->startOfDay(), now()->subDay()->endOfDay());
        $sevenDays = $this->overviewPeriodStats($user->id, now()->subDays(6)->startOfDay(), now());
        $hourlyRows = UsageLog::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay()->startOfDay())
            ->selectRaw("date_trunc('hour', created_at) as hour_bucket, count(*) requests, sum(input_tokens + output_tokens) tokens")
            ->groupByRaw("date_trunc('hour', created_at)")
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->hour_bucket)->format('Y-m-d H'));
        $hourly = collect(range(0, 23))->map(function (int $hour) use ($hourlyRows) {
            $current = now()->startOfDay()->addHours($hour);
            $previous = now()->subDay()->startOfDay()->addHours($hour);

            return [
                'label' => $current->format('H:i'),
                'today' => (int) ($hourlyRows->get($current->format('Y-m-d H'))->requests ?? 0),
                'yesterday' => (int) ($hourlyRows->get($previous->format('Y-m-d H'))->requests ?? 0),
            ];
        });
        $topModels = UsageLog::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDays(6)->startOfDay())->select('model')->selectRaw('count(*) requests, sum(input_tokens + output_tokens) tokens, sum(cost) cost')->groupBy('model')->orderByDesc('tokens')->limit(5)->get();
        $apiKeys = ApiKey::where('user_id', $user->id)->latest()->get();
        $keyStats = UsageLog::query()->where('user_id', $user->id)->whereIn('api_key_id', $apiKeys->pluck('id'))->where('created_at', '>=', now()->subDays(6)->startOfDay())->select('api_key_id')->selectRaw('count(*) requests, sum(input_tokens + output_tokens) tokens')->groupBy('api_key_id')->get()->keyBy('api_key_id');

        return view('dashboard', [
            'user' => $user,
            'apiKeys' => $apiKeys->take(5),
            'keyStats' => $keyStats,
            'activeKeys' => $apiKeys->where('is_active', true)->count(),
            'totalRequests' => (clone $usage)->count(),
            'totalTokens' => (clone $usage)->sum('input_tokens') + (clone $usage)->sum('output_tokens'),
            'totalCost' => (clone $usage)->sum('cost'),
            'recentUsage' => UsageLog::where('user_id', $user->id)->latest()->limit(8)->get(),
            'today' => $today,
            'yesterday' => $yesterday,
            'sevenDays' => $sevenDays,
            'hourly' => $hourly,
            'topModels' => $topModels,
        ]);
    }

    public function keys()
    {
        $apiKeys = ApiKey::where('user_id', Auth::id())->latest()->get();

        $usageStats = UsageLog::select('api_key_id')
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost) as cost')
            ->whereIn('api_key_id', $apiKeys->pluck('id'))
            ->groupBy('api_key_id')
            ->get()
            ->keyBy('api_key_id');

        return view('user.keys', [
            'apiKeys' => $apiKeys,
            'usageStats' => $usageStats,
            'totalRequests' => (int) $usageStats->sum('requests'),
            'totalCost' => (float) $usageStats->sum('cost'),
            'activeKeys' => $apiKeys->where('is_active', true)->count(),
        ]);
    }

    public function usage(Request $request)
    {
        $query = $this->usageQuery($request);

        $totalRequests = (clone $query)->count();
        $totalTokens = (int) ((clone $query)->sum('input_tokens') + (clone $query)->sum('output_tokens'));
        $totalCost = (float) (clone $query)->sum('cost');
        $errorCount = (clone $query)->where('status_code', '>=', 400)->count();
        $totalLatency = (float) (clone $query)->sum('latency_ms');

        return view('user.usage', array_merge([
            'usageLogs' => $query->with('apiKey')->latest()->paginate(25)->withQueryString(),
            'apiKeys' => ApiKey::where('user_id', Auth::id())->latest()->get(),
            'endpoints' => UsageLog::where('user_id', Auth::id())->select('endpoint')->distinct()->orderBy('endpoint')->pluck('endpoint'),
            'filters' => $request->only(['search', 'api_key_id', 'endpoint', 'status', 'from', 'to']),
            'totalRequests' => $totalRequests,
            'totalTokens' => $totalTokens,
            'totalCost' => $totalCost,
            'errorCount' => $errorCount,
            'avgLatency' => $totalRequests > 0 ? (int) round($totalLatency / $totalRequests) : 0,
        ], $this->usageCharts()));
    }

    public function export(Request $request)
    {
        $rows = $this->usageQuery($request)->with('apiKey')->latest()->limit(10000)->get();

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        fputcsv($out, ['Tanggal', 'Request ID', 'API Key', 'Model', 'Endpoint', 'Input Tokens', 'Output Tokens', 'Cost (USD)', 'Cache', 'Latency (ms)', 'Status', 'Source', 'IP Address', 'User Agent']);
        foreach ($rows as $log) {
            fputcsv($out, [
                $log->created_at?->format('Y-m-d H:i:s'),
                $log->request_id,
                $log->apiKey?->name,
                $log->model,
                $log->endpoint,
                $log->input_tokens,
                $log->output_tokens,
                (float) $log->cost,
                $log->cache_read ? 'hit' : ($log->cache_write ? 'write' : ''),
                $log->latency_ms,
                $log->status_code,
                $log->usage_source,
                $log->ip_address,
                $log->user_agent,
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="azkia-request-log.csv"',
        ]);
    }

    private function usageQuery(Request $request)
    {
        $query = UsageLog::where('user_id', Auth::id());

        $search = $request->input('search');
        if (is_string($search) && trim($search)) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($keyId = $request->input('api_key_id')) {
            $query->where('api_key_id', $keyId);
        }

        if ($endpoint = $request->input('endpoint')) {
            $query->where('endpoint', $endpoint);
        }

        if ($status = $request->input('status')) {
            if ($status === 'success') {
                $query->where('status_code', '<', 400);
            } elseif ($status === 'error') {
                $query->where('status_code', '>=', 400);
            } else {
                $query->where('status_code', (int) $status);
            }
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    public function billing()
    {
        $user = Auth::user();

        // Statistik PAYG bulan berjalan
        $monthUsage = UsageLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth());
        $monthRequests = (clone $monthUsage)->count();
        $monthTokens = (int) ((clone $monthUsage)->sum('input_tokens') + (clone $monthUsage)->sum('output_tokens'));
        $monthCost = (float) (clone $monthUsage)->sum('cost');

        // Estimasi sisa request berdasarkan biaya rata-rata per request BERTAGIH (cost > 0).
        // Request streaming tercatat dengan cost 0, sehingga diabaikan agar estimasi akurat.
        $billedQuery = (clone $monthUsage)->where('cost', '>', 0);
        $billedRequests = (clone $billedQuery)->count();
        $billedCost = (float) (clone $billedQuery)->sum('cost');
        $avgCostPerRequest = $billedRequests > 0 ? $billedCost / $billedRequests : 0.0;
        $estimatedRequests = $avgCostPerRequest > 0 ? (int) floor($user->balance / $avgCostPerRequest) : null;

        // Harga PAYG per model (pricing rule aktif terbaru per model)
        $pricingRules = PricingRule::where('is_active', true)
            ->orderBy('ai_model_id')
            ->orderByDesc('id')
            ->get()
            ->unique('ai_model_id')
            ->mapWithKeys(fn ($rule) => [$rule->ai_model_id => $rule]);

        $models = AiModel::with('provider')->where('is_active', true)->orderBy('public_name')->get();

        return view('user.billing', [
            'user' => $user,
            'transactions' => Transaction::where('user_id', $user->id)->latest()->paginate(10, ['*'], 'transactions_page'),
            'paymentOrders' => PaymentOrder::where('user_id', $user->id)->latest()->paginate(10, ['*'], 'payments_page'),
            'monthRequests' => $monthRequests,
            'monthTokens' => $monthTokens,
            'monthCost' => $monthCost,
            'avgCostPerRequest' => $avgCostPerRequest,
            'estimatedRequests' => $estimatedRequests,
            'pricingRules' => $pricingRules,
            'models' => $models,
        ]);
    }

    public function apiHealth()
    {
        $url = config('services.gateway_health_url');
        $health = null;
        $error = null;
        $latencyMs = null;

        try {
            $start = microtime(true);
            $response = Http::timeout(5)->acceptJson()->get($url);
            $latencyMs = (int) round((microtime(true) - $start) * 1000);
            if ($response->successful()) {
                $health = $response->json();
            } else {
                $error = __('dashboard.flash.gateway_http', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            $error = __('dashboard.flash.gateway_unreachable', ['message' => $e->getMessage()]);
        }

        return view('user.api-health', [
            'health' => $health,
            'error' => $error,
            'latencyMs' => $latencyMs,
        ]);
    }

    public function models()
    {
        $models = AiModel::with(['provider', 'latestPricingRule'])
            ->where('is_active', true)
            ->orderBy('public_name')
            ->get();

        // Kemampuan per model: pakai kolom capabilities (multi-modal), fallback ke type untuk model lama.
        $allCaps = $models
            ->flatMap(fn ($m) => $m->capabilities ?: [strtolower((string) $m->type)])
            ->map(fn ($c) => strtolower((string) $c))
            ->filter(fn ($c) => $c !== '')
            ->countBy();

        $capOrder = ['chat', 'completion', 'embedding', 'tool'];
        $capabilityGroups = collect($capOrder)
            ->filter(fn ($c) => isset($allCaps[$c]))
            ->mapWithKeys(fn ($c) => [$c => $allCaps[$c]])
            ->union($allCaps->except($capOrder));

        return view('user.models', [
            'models' => $models,
            'capabilityGroups' => $capabilityGroups,
        ]);
    }

    public function status()
    {
        $models = AiModel::with('provider', 'latestPricingRule')
            ->where('is_active', true)
            ->orderBy('public_name')
            ->get();

        // Statistik agregat per model dari usage_logs (kolom model = alias publik).
        $stats = UsageLog::select('model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->selectRaw('round(avg(latency_ms) filter (where status_code < 400), 1) as avg_latency')
            ->selectRaw('max(created_at) as last_used')
            ->groupBy('model')
            ->get()
            ->keyBy('model')
            ->each(fn ($s) => $s->last_used = $s->last_used ? Carbon::parse($s->last_used) : null);

        // Status per 10 menit untuk 5 jam terakhir (30 bucket) — timeline realtime.
        $daily = UsageLog::select('model')
            ->selectRaw("to_char(to_timestamp(floor(extract(epoch from created_at) / 600) * 600), 'YYYY-MM-DD HH24:MI') as bucket")
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->where('created_at', '>=', now()->subMinutes(300))
            ->groupBy('model', 'bucket')
            ->get()
            ->groupBy('model');

        // Ping realtime per model ke gateway /health/models.
        $live = null;
        $liveError = null;
        $liveCheckedAt = null;
        try {
            $request = Http::timeout(20)->acceptJson();
            if ($token = config('services.gateway_health_token')) {
                $request = $request->withHeaders(['X-Health-Token' => $token]);
            }
            $response = $request->get(config('services.gateway_health_models_url'));
            if ($response->successful() && is_array($response->json('data'))) {
                $live = collect($response->json('data'))->keyBy('model');
                $liveCheckedAt = $response->json('checked_at');
            } else {
                $liveError = 'Gateway merespons HTTP '.$response->status();
            }
        } catch (\Throwable $e) {
            $liveError = 'Gateway tidak terjangkau: '.$e->getMessage();
        }

        $liveDown = $live !== null && $live->contains(fn ($l) => ! (bool) ($l['reachable'] ?? false));
        $degraded = $models->contains(fn ($m) => $m->latestPricingRule
            && ($stats->get($m->public_name)?->errors ?? 0) > 0);

        if ($models->isEmpty()) {
            $overall = ['red', 'Tidak ada model aktif'];
        } elseif ($liveDown) {
            $overall = ['red', 'Ada model tidak dapat dijangkau'];
        } elseif ($degraded) {
            $overall = ['amber', 'Sebagian model bermasalah'];
        } else {
            $overall = ['green', 'Semua Sistem Operasional'];
        }

        return view('user.status', [
            'models' => $models,
            'stats' => $stats,
            'daily' => $daily,
            'live' => $live,
            'liveError' => $liveError,
            'liveCheckedAt' => $liveCheckedAt,
            'overall' => $overall,
            // 30 bucket, masing-masing 10 menit, diakhiri pada batas 10 menit terakhir.
            'days' => collect(range(29, 0))->map(function ($i) {
                $floor = now()->timestamp - (now()->timestamp % 600);

                return Carbon::createFromTimestamp($floor - $i * 600);
            }),
            'totalRequests' => UsageLog::count(),
            'totalErrors' => UsageLog::where('status_code', '>=', 400)->count(),
        ]);
    }

    public function leaderboard(Request $request)
    {
        // Rentang waktu pemakaian: hari ini / 7 hari / 30 hari / semua waktu.
        $range = $request->input('range', 'all');
        $from = match ($range) {
            'today' => now()->startOfDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => null,
        };

        $logs = UsageLog::select('model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')   // ranking: total token
            ->selectRaw('sum(cost) as cost')
            ->selectRaw('max(created_at) as last_used')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->limit(20)
            ->get();

        $leaderboard = $logs->map(function ($row) {
            $row->tokens = (int) $row->tokens;
            $row->requests = (int) $row->requests;
            $row->last_used = $row->last_used ? Carbon::parse($row->last_used) : null;

            return $row;
        })->values();

        return view('user.leaderboard', [
            'leaderboard' => $leaderboard,
            'range' => $range,
            'totalRequests' => (int) $logs->sum('requests'),
            'totalTokens' => (int) $logs->sum('tokens'),
            'totalCost' => (float) $logs->sum('cost'),
        ]);
    }

    public function plans()
    {
        $user = Auth::user();

        return view('user.plans', [
            'user' => $user,
            // Plan gratis (reset harian) tidak dijual — otomatis aktif untuk semua user.
            'available' => Plan::where('is_active', true)->where('resets_daily', false)->with('models')->orderBy('price_usd')->get(),
            'userPlans' => UserPlan::with('plan')->where('user_id', $user->id)->latest('purchased_at')->paginate(10),
            'freePlan' => $user->activePlans()->where('resets_daily', true)->with('plan')->first(),
            'activePlanTokens' => $user->plan_tokens_remaining,
            'usdRate' => usd_to_idr_rate(),
        ]);
    }

    public function purchasePlan(Request $request, Plan $plan)
    {
        abort_unless($plan->is_active, 422, 'Plan tidak tersedia.');
        // Plan gratis (reset harian) otomatis aktif untuk semua user — tidak perlu dibeli.
        abort_unless(! $plan->resets_daily, 422, 'Plan gratis sudah otomatis aktif.');

        DB::transaction(function () use ($plan) {
            // Lock baris plan agar pengurangan stok atomik (tidak over-sell).
            $plan = Plan::whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($plan->is_active, 422, 'Plan tidak tersedia.');

            if ($plan->stock !== null) {
                if ($plan->stock <= 0) {
                    throw ValidationException::withMessages(['plan' => __('dashboard.flash.plan_sold_out')]);
                }
                $plan->decrement('stock');
            }

            $user = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $price = (string) $plan->price_usd;

            if (bccomp((string) $user->balance, $price, 6) < 0) {
                throw ValidationException::withMessages(['plan' => __('dashboard.flash.insufficient_balance')]);
            }

            $balanceBefore = (string) $user->balance;
            $user->balance = bcsub($balanceBefore, $price, 6);
            $user->save();

            UserPlan::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'tokens_remaining' => $plan->total_tokens,
                'daily_limit_tokens' => $plan->daily_limit_tokens,
                'daily_tokens_used' => 0,
                // Plan tanpa duration_hours ("Tanpa masa berlaku") tidak kedaluwarsa.
                'expires_at' => $plan->duration_hours !== null ? now()->addHours($plan->duration_hours) : null,
                'purchased_at' => now(),
                'status' => 'active',
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'plan_purchase',
                'amount' => '-'.$price,
                'balance_before' => $balanceBefore,
                'balance_after' => $user->balance,
                'currency' => 'USD',
                'status' => 'completed',
                'reference' => 'plan-'.$plan->id.'-'.Str::uuid(),
                'notes' => 'Pembelian plan '.$plan->name.' ('.format_compact_number($plan->total_tokens).' token)',
            ]);

            // Tampilkan masa berlaku dalam waktu WIB (Asia/Jakarta); plan tanpa
            // durasi ("Tanpa masa berlaku") tidak punya tanggal kedaluwarsa.
            $expiryText = $plan->duration_hours !== null
                ? 'aktif hingga '.now()->addHours($plan->duration_hours)->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB'
                : 'berlaku tanpa batas waktu';

            InboxMessage::create([
                'user_id' => $user->id,
                'sender_id' => null,
                'subject' => 'Plan berhasil diaktifkan',
                'body' => 'Plan '.$plan->name.' ('.format_compact_number($plan->total_tokens).' token) '.$expiryText.'.',
            ]);
        });

        return redirect()->route('plans')->with('success', __('dashboard.flash.plan_purchased', ['plan' => $plan->name]));
    }

    public function updatePayg(Request $request)
    {
        $data = $request->validate([
            'payg_enabled' => ['required', 'boolean'],
        ]);

        Auth::user()->update(['payg_enabled' => (bool) $data['payg_enabled']]);

        return back()->with('success', __('dashboard.flash.payg_updated'));
    }

    public function settings()
    {
        return view('user.settings', ['user' => Auth::user()]);
    }

    public function referral()
    {
        $user = Auth::user();

        // Defensif: pastikan user punya kode referral (user lama dari backfill).
        if (! $user->referral_code) {
            $user->update(['referral_code' => User::generateReferralCode()]);
        }

        $referrals = User::where('referred_by', $user->id)->get();

        return view('user.referral', [
            'referralCode' => $user->referral_code,
            'referralLink' => url('/?ref='.$user->referral_code),
            'totalReferrals' => $referrals->count(),
            'pendingReferrals' => $referrals->whereNull('referral_rewarded_at')->count(),
            'totalEarned' => (string) Transaction::where('user_id', $user->id)->where('type', 'referral_reward')->sum('amount'),
            'rewardText' => '$'.number_format((float) config('referral.reward_usd'), 2),
            'minTopupText' => 'Rp '.number_format((int) config('referral.min_topup_idr'), 0, ',', '.'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email']]);

        return back()->with('success', __('dashboard.flash.profile_updated'));
    }

    public function usageCharts()
    {
        $user = Auth::user();

        // Tren per hari untuk 14 hari terakhir: token (input/output terpisah),
        // biaya, dan request — untuk grafik dengan toggle metrik.
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $daily = UsageLog::selectRaw("to_char(created_at, 'YYYY-MM-DD') as day")
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->selectRaw('sum(input_tokens) as input_tokens')
            ->selectRaw('sum(output_tokens) as output_tokens')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost) as cost')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $dailySeries = $days->map(fn ($day) => [
            'day' => $day,
            'label' => Carbon::parse($day)->format('d/m'),
            'short' => Carbon::parse($day)->format('D'),
            'requests' => (int) ($daily->get($day)?->requests ?? 0),
            'errors' => (int) ($daily->get($day)?->errors ?? 0),
            'input_tokens' => (int) ($daily->get($day)?->input_tokens ?? 0),
            'output_tokens' => (int) ($daily->get($day)?->output_tokens ?? 0),
            'tokens' => (int) ($daily->get($day)?->tokens ?? 0),
            'cost' => (float) ($daily->get($day)?->cost ?? 0),
        ]);

        // Breakdown per model (30 hari terakhir): token, biaya, request, error.
        $perModel = UsageLog::select('model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->selectRaw('sum(input_tokens) as input_tokens')
            ->selectRaw('sum(output_tokens) as output_tokens')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost) as cost')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('model')
            ->orderByDesc('cost')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'model' => $row->model,
                'requests' => (int) $row->requests,
                'errors' => (int) $row->errors,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'tokens' => (int) $row->tokens,
                'cost' => (float) $row->cost,
            ])
            ->values();

        return [
            'daily' => $dailySeries,
            'perModel' => $perModel,
            'maxDailyTokens' => max(1, (int) $dailySeries->max('tokens')),
            'maxDailyInput' => max(1, (int) $dailySeries->max('input_tokens')),
            'maxDailyOutput' => max(1, (int) $dailySeries->max('output_tokens')),
            'maxDailyRequests' => max(1, (int) $dailySeries->max('requests')),
            'maxDailyCost' => max(0.000001, (float) $dailySeries->max('cost')),
            'maxModelTokens' => max(1, (int) $perModel->max('tokens')),
            'maxModelCost' => max(0.000001, (float) $perModel->max('cost')),
        ];
    }

    private function overviewPeriodStats(int $userId, $start, $end): object
    {
        return UsageLog::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('count(*) requests, count(*) filter (where status_code >= 400) errors, coalesce(sum(input_tokens), 0) input_tokens, coalesce(sum(output_tokens), 0) output_tokens, coalesce(sum(cost), 0) cost, coalesce(avg(latency_ms), 0) avg_latency, count(*) filter (where cache_read = true) cache_hits')
            ->first();
    }

    public function docs()
    {
        return view('user.docs');
    }
}

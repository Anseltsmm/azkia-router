<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\ApiKey;
use App\Models\InboxMessage;
use App\Models\PricingRule;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index', [
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'active')->count(),
            'totalProviders' => Provider::count(),
            'activeModels' => AiModel::where('is_active', true)->count(),
            'pricingCount' => PricingRule::where('is_active', true)->count(),
            'apiKeyCount' => ApiKey::where('is_active', true)->count(),
            'totalRevenue' => Transaction::where('type', 'topup')->sum('amount'),
            'totalUsageCost' => UsageLog::sum('cost'),
            'totalRequests' => UsageLog::count(),
        ]);
    }

    public function providers()
    {
        return view('admin.providers', [
            'providers' => Provider::withCount('aiModels')->latest()->get(),
        ]);
    }

    public function models()
    {
        return view('admin.models', [
            'models' => AiModel::with('provider', 'latestPricingRule')->latest()->get(),
            'providers' => Provider::orderBy('name')->get(),
        ]);
    }

    public function pricing()
    {
        return view('admin.pricing', [
            'pricingRules' => PricingRule::with('aiModel')->latest()->get(),
            'models' => AiModel::orderBy('public_name')->get(),
            // Kurs realtime untuk menampilkan pasangan IDR saat input harga USD.
            'usdRate' => usd_to_idr_rate(),
        ]);
    }

    public function status()
    {
        $models = AiModel::with('provider', 'latestPricingRule')->orderBy('public_name')->get();

        // Statistik agregat per model dari usage_logs (kolom model = alias publik).
        $stats = UsageLog::select('model')
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->selectRaw('round(avg(latency_ms) filter (where status_code < 400), 1) as avg_latency')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost) as cost')
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

        $hasActive = $models->where('is_active', true)->isNotEmpty();
        $liveDown = $live !== null && $live->contains(fn ($l) => ! (bool) ($l['reachable'] ?? false));
        $degraded = $models->contains(fn ($m) => $m->is_active
            && (! $m->latestPricingRule || ($stats->get($m->public_name)?->errors ?? 0) > 0));

        if (! $hasActive) {
            $overall = ['red', 'Semua model nonaktif'];
        } elseif ($liveDown) {
            $overall = ['red', 'Ada model tidak dapat dijangkau'];
        } elseif ($degraded) {
            $overall = ['amber', 'Sebagian model bermasalah'];
        } else {
            $overall = ['green', 'Semua Sistem Operasional'];
        }

        return view('admin.status', [
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

    public function keys()
    {
        return view('admin.keys', [
            'apiKeys' => ApiKey::with('user')->latest()->get(),
        ]);
    }

    /**
     * Audit trail request API yang ditolak sebelum billing (auth, rate limit,
     * model, capability) — dicatat gateway ke tabel request_rejections.
     */
    public function rejections(Request $request)
    {
        $query = DB::table('request_rejections')
            ->leftJoin('users', 'users.id', '=', 'request_rejections.user_id')
            ->select('request_rejections.*', 'users.email as user_email');

        $status = $request->input('status');
        $search = trim((string) $request->input('search'));

        if ($status !== null && $status !== '') {
            $query->where('request_rejections.status_code', (int) $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('request_rejections.model', 'ilike', "%{$search}%")
                    ->orWhere('request_rejections.endpoint', 'ilike', "%{$search}%")
                    ->orWhere('request_rejections.reason', 'ilike', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('request_rejections.created_at')->paginate(25)->withQueryString();

        $summary = DB::table('request_rejections')
            ->select('status_code', DB::raw('count(*) as total'))
            ->groupBy('status_code')
            ->orderByDesc('total')
            ->get();

        return view('admin.rejections', [
            'rows' => $rows,
            'summary' => $summary,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function users(Request $request)
    {
        $query = User::query();
        $search = trim((string) $request->input('search'));
        if ($search) {
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"));
        }

        $users = $query->withCount(['apiKeys' => fn ($q) => $q->where('is_active', true)])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Statistik pemakaian per user (requests, errors, tokens, cost, terakhir dipakai).
        $stats = UsageLog::select('user_id')
            ->selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost) as cost')
            ->selectRaw('max(created_at) as last_used')
            ->whereIn('user_id', $users->pluck('id'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id')
            ->each(fn ($s) => $s->last_used = $s->last_used ? Carbon::parse($s->last_used) : null);

        return view('admin.users', [
            'users' => $users,
            'stats' => $stats,
            'search' => $search,
        ]);
    }

    public function userDetail(User $user)
    {
        $row = UsageLog::selectRaw('count(*) as requests')
            ->selectRaw('count(*) filter (where status_code >= 400) as errors')
            ->selectRaw('sum(input_tokens + output_tokens) as tokens')
            ->selectRaw('sum(cost) as cost')
            ->selectRaw('max(created_at) as last_used')
            ->where('user_id', $user->id)
            ->first();
        $row->last_used = $row->last_used ? Carbon::parse($row->last_used) : null;

        return view('admin.users-show', [
            'user' => $user,
            'stats' => $row,
            'apiKeys' => $user->apiKeys()->latest()->get(),
            'transactions' => $user->transactions()->latest()->limit(20)->get(),
            'recentUsage' => $user->usageLogs()->latest()->limit(10)->get(),
        ]);
    }

    public function editUser(User $user)
    {
        return view('admin.users-edit', ['user' => $user]);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'is_admin' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_admin = $request->boolean('is_admin');
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password']; // cast hashed
        }
        $user->save();

        return redirect()->route('admin.users.show', $user)->with('success', 'User diperbarui.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User dihapus.');
    }

    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url'],
            'api_key' => ['nullable', 'string'],
        ]);

        Provider::create([
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'api_key_encrypted' => filled($data['api_key'] ?? null) ? Crypt::encryptString($data['api_key']) : null,
        ]);

        return back()->with('success', 'Provider ditambahkan.');
    }

    public function storeModel(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'exists:providers,id'],
            'public_name' => ['required', 'string', 'max:255', 'unique:ai_models,public_name'],
            'upstream_name' => ['required', 'string', 'max:255'],
            // Model bisa multi-modal; type diturunkan dari kemampuan pertama.
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:50'],
            'context_window' => ['nullable', 'integer', 'min:1'],
            // Batas request per menit per API key per model; kosong = tanpa batas.
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            // mimes (bukan image) agar SVG juga diterima — getimagesize gagal untuk SVG.
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'], // KB (5 MB)
        ]);

        $capabilities = array_values(array_unique($data['capabilities'] ?? []));
        $data['capabilities'] = $capabilities ?: null;
        $data['type'] = $capabilities[0] ?? 'chat';
        $data['icon_path'] = $this->storeModelIcon($request);
        $data['rate_limit_per_minute'] = filled($data['rate_limit_per_minute'] ?? null) ? (int) $data['rate_limit_per_minute'] : null;

        AiModel::create($data);

        return back()->with('success', 'Model ditambahkan.');
    }

    public function editModel(AiModel $model)
    {
        $pricingRule = $model->latestPricingRule;

        return view('admin.models-edit', [
            'model' => $model,
            'providers' => Provider::orderBy('name')->get(),
            'pricingRule' => $pricingRule,
            'normalInputPrice' => $pricingRule?->is_promo ? $pricingRule->original_input_per_million : $pricingRule?->input_per_million,
            'normalOutputPrice' => $pricingRule?->is_promo ? $pricingRule->original_output_per_million : $pricingRule?->output_per_million,
            'usdRate' => usd_to_idr_rate(),
        ]);
    }

    public function updateModel(Request $request, AiModel $model)
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'exists:providers,id'],
            'public_name' => ['required', 'string', 'max:255', Rule::unique('ai_models')->ignore($model->id)],
            'upstream_name' => ['required', 'string', 'max:255'],
            // Model bisa multi-modal; type diturunkan dari kemampuan pertama.
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:50'],
            'context_window' => ['nullable', 'integer', 'min:1'],
            // Batas request per menit per API key per model; kosong = tanpa batas.
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            // mimes (bukan image) agar SVG juga diterima — getimagesize gagal untuk SVG.
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'], // KB (5 MB)
            'remove_icon' => ['sometimes', 'boolean'],
        ]);

        $capabilities = array_values(array_unique($data['capabilities'] ?? []));
        $data['capabilities'] = $capabilities ?: null;
        $data['type'] = $capabilities[0] ?? 'chat';
        $data['is_active'] = $request->boolean('is_active');
        $data['rate_limit_per_minute'] = filled($data['rate_limit_per_minute'] ?? null) ? (int) $data['rate_limit_per_minute'] : null;

        if ($request->boolean('remove_icon')) {
            $this->deleteModelIcon($model);
            $data['icon_path'] = null;
        } elseif ($request->hasFile('icon')) {
            $this->deleteModelIcon($model);
            $data['icon_path'] = $this->storeModelIcon($request);
        }

        $model->update($data);

        return redirect()->route('admin.models')->with('success', 'Model diperbarui.');
    }

    /**
     * Simpan ikon model ke disk public (model-icons/) dan kembalikan path-nya.
     * Nama file unik agar tidak bentrok & cache browser aman.
     */
    private function storeModelIcon(Request $request): ?string
    {
        if (! $request->hasFile('icon')) {
            return null;
        }

        $file = $request->file('icon');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = 'model-'.now()->format('YmdHis').'-'.Str::random(8).'.'.$ext;

        $path = $file->storeAs('model-icons', $name, 'public');

        return $path ?: null;
    }

    private function deleteModelIcon(AiModel $model): void
    {
        if ($model->icon_path) {
            Storage::disk('public')->delete($model->icon_path);
        }
    }

    public function destroyModel(AiModel $model)
    {
        // Pricing rules terhapus otomatis via cascade FK (ai_model_id).
        $model->delete();

        return redirect()->route('admin.models')->with('success', 'Model dihapus.');
    }

    public function storePricing(Request $request)
    {
        $data = $request->validate(array_merge([
            'ai_model_id' => ['required', 'exists:ai_models,id'],
        ], $this->pricingValidationRules()));

        $this->createPricingRule((int) $data['ai_model_id'], $data, $request->boolean('is_promo'));

        return back()->with('success', 'Pricing rule ditambahkan.');
    }

    public function updateModelPricing(Request $request, AiModel $model)
    {
        $data = $request->validate($this->pricingValidationRules());
        $this->createPricingRule($model->id, $data, $request->boolean('is_promo'));

        return back()->with('success', 'Pricing model berhasil diperbarui.');
    }

    private function pricingValidationRules(): array
    {
        return [
            'input_per_million' => ['required', 'numeric', 'min:0'],
            'output_per_million' => ['required', 'numeric', 'min:0'],
            'cache_read_input_per_million' => ['nullable', 'numeric', 'min:0'],
            'cache_write_per_million' => ['nullable', 'numeric', 'min:0'],
            'is_promo' => ['sometimes', 'boolean'],
            'promo_input_per_million' => ['nullable', 'required_if:is_promo,1', 'numeric', 'min:0', 'lte:input_per_million'],
            'promo_output_per_million' => ['nullable', 'required_if:is_promo,1', 'numeric', 'min:0', 'lte:output_per_million'],
            'promo_starts_at' => ['nullable', 'date', 'required_if:is_promo,1'],
            'promo_ends_at' => ['nullable', 'date', 'required_if:is_promo,1', 'after:promo_starts_at'],
        ];
    }

    private function createPricingRule(int $modelId, array $data, bool $isPromo): PricingRule
    {
        return PricingRule::create([
            'ai_model_id' => $modelId,
            'input_per_million' => $isPromo ? $data['promo_input_per_million'] : $data['input_per_million'],
            'output_per_million' => $isPromo ? $data['promo_output_per_million'] : $data['output_per_million'],
            'original_input_per_million' => $isPromo ? $data['input_per_million'] : null,
            'original_output_per_million' => $isPromo ? $data['output_per_million'] : null,
            'cache_read_input_per_million' => filled($data['cache_read_input_per_million'] ?? null) ? $data['cache_read_input_per_million'] : null,
            'cache_write_per_million' => filled($data['cache_write_per_million'] ?? null) ? $data['cache_write_per_million'] : null,
            'currency' => 'USD',
            'is_promo' => $isPromo,
            'promo_starts_at' => $isPromo ? Carbon::parse($data['promo_starts_at'], 'Asia/Jakarta')->utc() : null,
            'promo_ends_at' => $isPromo ? Carbon::parse($data['promo_ends_at'], 'Asia/Jakarta')->utc() : null,
        ]);
    }

    public function topupUser(Request $request, User $user)
    {
        $data = $request->validate([
            // Nominal yang dibayar user dalam Rupiah; dikonversi ke USD dengan kurs realtime.
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $idrAmount = (string) $data['amount'];
        $rate = (string) app(ExchangeRateService::class)->usdToIdr();
        $usdAmount = bcdiv($idrAmount, $rate, 6);

        DB::transaction(function () use ($data, $idrAmount, $rate, $usdAmount, $user) {
            $lockedUser = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $lockedUser->balance = bcadd((string) $lockedUser->balance, $usdAmount, 6);
            $lockedUser->save();

            $notes = trim($data['notes'] ?? '');
            $notes = trim($notes.' Topup Rp '.number_format($idrAmount, 0, ',', '.').' (kurs 1 USD = Rp '.number_format($rate, 0, ',', '.').')');

            Transaction::create([
                'user_id' => $lockedUser->id,
                'type' => 'topup',
                'amount' => $usdAmount,
                'balance_after' => $lockedUser->balance,
                'currency' => 'USD',
                'status' => 'completed',
                'reference' => 'manual-'.Str::uuid(),
                'notes' => $notes,
            ]);
        });

        return back();
    }

    public function sendInboxMessage(Request $request, User $user)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        InboxMessage::create([
            'user_id' => $user->id,
            'sender_id' => auth()->id(),
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Pesan berhasil dikirim ke inbox user.');
    }

    public function updateUserStatus(Request $request, User $user)
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $user->update(['status' => $data['status']]);

        return back();
    }

    public function updateApiKey(Request $request, ApiKey $apiKey)
    {
        $data = $request->validate([
            'rate_limit_per_minute' => ['required', 'integer', 'min:1'],
            'monthly_quota_tokens' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $apiKey->update([
            'rate_limit_per_minute' => $data['rate_limit_per_minute'],
            'monthly_quota_tokens' => $data['monthly_quota_tokens'] ?? null,
            'expires_at' => $request->date('expires_at')?->endOfDay(),
        ]);

        return back();
    }

    public function toggleApiKey(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        return back();
    }

    public function toggleProvider(Provider $provider)
    {
        $provider->update(['is_active' => ! $provider->is_active]);

        return back();
    }

    public function toggleModel(AiModel $model)
    {
        $model->update(['is_active' => ! $model->is_active]);

        return back();
    }
}

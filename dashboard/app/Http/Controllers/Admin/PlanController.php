<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans', [
            'plans' => Plan::withCount('userPlans')->with('models')->latest()->paginate(20),
            'usdRate' => usd_to_idr_rate(),
            // Pilihan model aktif untuk pembatasan per-plan.
            'models' => AiModel::where('is_active', true)->orderBy('public_name')->get(['id', 'public_name']),
        ]);
    }

    public function store(Request $request)
    {
        $plan = Plan::create($this->validated($request, null));
        $this->syncModels($plan, $request->input('model_ids'));

        return back()->with('success', 'Plan berhasil dibuat.');
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));
        $this->syncModels($plan, $request->input('model_ids'));

        return back()->with('success', 'Plan berhasil diperbarui.');
    }

    /**
     * Simpan pembatasan model untuk plan (berlaku juga untuk plan gratis
     * reset harian). Plan tanpa pilihan model = semua model.
     */
    private function syncModels(Plan $plan, ?array $modelIds): void
    {
        $plan->models()->sync($modelIds ?? []);
    }

    public function toggle(Plan $plan)
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', 'Status plan berhasil diperbarui.');
    }

    public function destroy(Plan $plan)
    {
        // Soft delete: plan hilang dari daftar & tidak bisa dibeli lagi,
        // tapi user_plans (kuota yang sudah dibeli) tetap utuh sampai kedaluwarsa.
        $plan->delete();

        return back()->with('success', 'Plan dihapus. Pembeli tetap bisa memakai kuotanya sampai kedaluwarsa.');
    }

    private function validated(Request $request, ?Plan $plan): array
    {
        $resetsDaily = $request->boolean('resets_daily');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('plans')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'total_tokens' => ['required', 'integer', 'min:1'],
            'daily_limit_tokens' => ['nullable', 'integer', 'min:1', 'lte:total_tokens'],
            // Batas request per menit per user per plan; kosong = tanpa batas.
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            // Nullable: '' ("Tanpa masa berlaku") valid untuk semua plan, termasuk berbayar.
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_idr' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'resets_daily' => ['sometimes', 'boolean'],
            // Stok penjualan: kosong = tanpa batas, angka = kuota terbatas.
            'stock' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            // Model yang boleh memakai plan ini (kosong = semua model).
            'model_ids' => ['nullable', 'array'],
            'model_ids.*' => ['integer', 'exists:ai_models,id'],
        ]);

        $data['slug'] = filled($data['slug'] ?? null) ? $data['slug'] : $this->generateUniqueSlug($data['name'], $plan?->id);
        $data['daily_limit_tokens'] = filled($data['daily_limit_tokens'] ?? null) ? $data['daily_limit_tokens'] : null;
        $data['rate_limit_per_minute'] = filled($data['rate_limit_per_minute'] ?? null) ? (int) $data['rate_limit_per_minute'] : null;
        // Kosong ("Tanpa masa berlaku") disimpan sebagai null, bukan 0 — kalau 0,
        // plan langsung kedaluwarsa saat dibeli (now()->addHours(0)).
        $data['duration_hours'] = filled($data['duration_hours'] ?? null) ? (int) $data['duration_hours'] : null;
        $data['is_active'] = $request->boolean('is_active');
        $data['resets_daily'] = $resetsDaily;
        $data['stock'] = filled($data['stock'] ?? null) ? (int) $data['stock'] : null;
        // Dipakai terpisah oleh syncModels(), bukan atribut model.
        unset($data['model_ids']);

        if ($resetsDaily) {
            // Plan gratis harian: tanpa masa berlaku, gratis, kuota = batas harian,
            // dan tidak dijual sehingga tidak punya stok.
            $data['duration_hours'] = null;
            $data['price_usd'] = 0;
            $data['price_idr'] = 0;
            $data['daily_limit_tokens'] = $data['daily_limit_tokens'] ?? $data['total_tokens'];
            $data['stock'] = null;
        } else {
            $data['price_idr'] = filled($data['price_idr'] ?? null) ? $data['price_idr'] : null;
        }

        return $data;
    }

    /**
     * Generate slug otomatis dari nama, dijamin unik terhadap plan lain.
     * Bila slug hasil generate sudah dipakai, ditambah akhiran -2, -3, dst.
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 2;
        while (Plan::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.($i++);
        }

        return $slug;
    }
}

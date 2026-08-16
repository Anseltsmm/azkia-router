<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\InboxMessage;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlanFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kurs realtime distub agar deterministik tanpa koneksi eksternal.
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['IDR' => 16250]], 200),
        ]);
    }

    private function makeFreePlan(): Plan
    {
        // Migration add_free_daily_plan sudah meng-insert plan ini di pgsql,
        // jadi idempotent (firstOrCreate) agar aman di semua driver.
        return Plan::firstOrCreate(['slug' => 'free-daily'], [
            'name' => 'Free Harian',
            'total_tokens' => 7000000,
            'daily_limit_tokens' => 7000000,
            'duration_hours' => null,
            'price_usd' => 0,
            'price_idr' => 0,
            'is_active' => true,
            'resets_daily' => true,
        ]);
    }

    private function makePaidPlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plan Harian 15M',
            'slug' => 'daily-15m',
            'total_tokens' => 15000000,
            'daily_limit_tokens' => 15000000,
            'duration_hours' => 24,
            'price_usd' => 0.70,
            'price_idr' => 11375,
            'is_active' => true,
            'resets_daily' => false,
        ], $overrides));
    }

    private function purchase(User $user, Plan $plan): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->post(route('plans.purchase', $plan));
    }

    // --- Free daily plan ---

    public function test_register_is_disabled(): void
    {
        $this->makeFreePlan();

        // Halaman register dinonaktifkan: /register redirect ke login (akun baru lewat Google).
        $this->get(route('register'))->assertRedirect(route('login'));

        // POST register tidak ada lagi — tidak ada akun yang bisa dibuat lewat form.
        $this->post(route('register'), [
            'name' => 'User Baru',
            'email' => 'baru@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertStatus(405);

        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
    }

    public function test_plans_page_shows_free_plan_and_remaining(): void
    {
        $this->makeFreePlan();
        $user = User::factory()->create();
        Plan::grantFreePlan($user);

        $this->actingAs($user)->get(route('plans'))
            ->assertOk()
            ->assertSee('Free Harian')
            ->assertSee('7M');
    }

    public function test_free_plan_remaining_resets_after_daily_reset(): void
    {
        $this->makeFreePlan();
        $user = User::factory()->create();
        $userPlan = Plan::grantFreePlan($user);

        // Simulasi pemakaian oleh gateway: counter harian naik, pool tetap penuh.
        $userPlan->update(['daily_tokens_used' => 3000000, 'daily_reset_date' => now()->toDateString()]);
        $this->assertSame(4000000, $userPlan->fresh()->remaining_tokens);

        // Hari berikutnya → counter di-reset (mengikuti tanggal server).
        $userPlan->update(['daily_reset_date' => now()->subDay()->toDateString()]);
        $this->assertSame(7000000, $userPlan->fresh()->remaining_tokens);
        $this->assertSame(0, $userPlan->fresh()->tokens_used);
    }

    // --- Halaman & pembelian plan user ---

    public function test_plans_page_lists_paid_plans_for_purchase(): void
    {
        $plan = $this->makePaidPlan();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('plans'))
            ->assertOk()
            ->assertSee($plan->name)
            ->assertSee('$0.70');
    }

    public function test_free_plan_is_not_listed_for_purchase(): void
    {
        $this->makeFreePlan();
        $user = User::factory()->create();
        Plan::grantFreePlan($user);

        $this->actingAs($user)->get(route('plans'))
            ->assertOk()
            ->assertSee('Free Harian')
            ->assertDontSee('Beli Sekarang');
    }

    public function test_purchase_deducts_balance_creates_plan_and_notifies(): void
    {
        $plan = $this->makePaidPlan(['price_usd' => 2.00]);
        $user = User::factory()->create(['balance' => 10]);

        $this->purchase($user, $plan)->assertRedirect(route('plans'));

        $this->assertSame('8.000000', $user->fresh()->balance);

        $userPlan = $user->plans()->firstOrFail();
        $this->assertSame($plan->id, $userPlan->plan_id);
        $this->assertSame($plan->total_tokens, $userPlan->tokens_remaining);
        $this->assertSame('active', $userPlan->status);
        $this->assertFalse($userPlan->resets_daily);
        $this->assertNotNull($userPlan->expires_at);
        $this->assertSame($plan->daily_limit_tokens, $userPlan->daily_limit_tokens);

        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('type', 'plan_purchase')->count());
        $this->assertSame(1, InboxMessage::where('user_id', $user->id)->count());
    }

    public function test_purchase_rejected_when_balance_insufficient(): void
    {
        $plan = $this->makePaidPlan(['price_usd' => 5.00]);
        $user = User::factory()->create(['balance' => 1]);

        $this->purchase($user, $plan)->assertSessionHasErrors('plan');

        $this->assertSame('1.000000', $user->fresh()->balance);
        $this->assertSame(0, $user->plans()->count());
        $this->assertSame(0, Transaction::count());
    }

    public function test_purchase_rejected_for_free_daily_plan(): void
    {
        $free = $this->makeFreePlan();
        $user = User::factory()->create(['balance' => 10]);

        $this->purchase($user, $free)->assertStatus(422);

        $this->assertSame('10.000000', $user->fresh()->balance);
        $this->assertSame(0, $user->plans()->count());
    }

    public function test_purchase_rejected_for_inactive_plan(): void
    {
        $plan = $this->makePaidPlan(['is_active' => false]);
        $user = User::factory()->create(['balance' => 10]);

        $this->purchase($user, $plan)->assertStatus(422);

        $this->assertSame('10.000000', $user->fresh()->balance);
        $this->assertSame(0, $user->plans()->count());
    }

    public function test_payg_toggle_updates_user(): void
    {
        $user = User::factory()->create(['payg_enabled' => true]);

        $this->actingAs($user)->patch(route('settings.payg'), ['payg_enabled' => 0])->assertRedirect();

        $this->assertFalse($user->fresh()->payg_enabled);
    }

    // --- Admin plan management ---

    public function test_admin_can_create_paid_plan(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan Pro 100M',
            'slug' => 'pro-100m',
            'total_tokens' => 100000000,
            'daily_limit_tokens' => 20000000,
            'duration_hours' => 168,
            'price_usd' => 5.00,
            'price_idr' => 81250,
            'is_active' => 1,
        ])->assertRedirect();

        $plan = Plan::where('slug', 'pro-100m')->firstOrFail();
        $this->assertSame(168, $plan->duration_hours);
        $this->assertSame('5.000000', $plan->price_usd);
        $this->assertSame(20000000, $plan->daily_limit_tokens);
        $this->assertFalse($plan->resets_daily);
        $this->assertTrue($plan->is_active);
    }

    /**
     * Regression: opsi "Tanpa masa berlaku" di dropdown durasi (value '')
     * harus valid untuk plan berbayar, bukan hanya plan gratis — sebelumnya
     * required_unless:resets_daily,1 menolaknya di validasi controller.
     */
    public function test_admin_can_create_paid_plan_without_duration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan Seumur Hidup',
            'total_tokens' => 10000000,
            'duration_hours' => '',
            'price_usd' => 10.00,
            'is_active' => 1,
        ])->assertRedirect();

        $plan = Plan::where('name', 'Plan Seumur Hidup')->firstOrFail();
        $this->assertNull($plan->duration_hours);
    }

    public function test_admin_can_update_paid_plan_to_no_duration(): void
    {
        $plan = $this->makePaidPlan();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'total_tokens' => $plan->total_tokens,
            'duration_hours' => '',
            'price_usd' => $plan->price_usd,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull($plan->fresh()->duration_hours);
    }

    public function test_admin_free_plan_forces_zero_price_and_no_duration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Free Mingguan',
            'total_tokens' => 50000000,
            'daily_limit_tokens' => 10000000,
            'duration_hours' => 168,
            'price_usd' => 9.99,
            'price_idr' => 100000,
            'resets_daily' => 1,
            'is_active' => 1,
            'stock' => 5,
        ])->assertRedirect();

        $plan = Plan::where('name', 'Free Mingguan')->firstOrFail();
        $this->assertTrue($plan->resets_daily);
        $this->assertNull($plan->duration_hours);
        $this->assertSame('0.000000', $plan->price_usd);
        $this->assertSame(10000000, $plan->daily_limit_tokens);
        // Plan gratis tidak dijual → tidak punya stok.
        $this->assertNull($plan->stock);
    }

    public function test_admin_can_create_plan_with_stock(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan Limited 50',
            'total_tokens' => 10000000,
            'duration_hours' => 24,
            'price_usd' => 1.00,
            'is_active' => 1,
            'stock' => 50,
        ])->assertRedirect();

        $plan = Plan::where('name', 'Plan Limited 50')->firstOrFail();
        $this->assertSame(50, $plan->stock);
        $this->assertFalse($plan->is_sold_out);

        // Tanpa nilai stock = tanpa batas (null).
        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan Unlimited',
            'total_tokens' => 10000000,
            'duration_hours' => 24,
            'price_usd' => 1.00,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull(Plan::where('name', 'Plan Unlimited')->firstOrFail()->stock);
    }

    public function test_purchase_decrements_plan_stock(): void
    {
        $plan = $this->makePaidPlan(['price_usd' => 1.00, 'stock' => 3]);
        $user = User::factory()->create(['balance' => 10]);

        $this->purchase($user, $plan)->assertRedirect(route('plans'));

        $this->assertSame(2, $plan->fresh()->stock);
        $this->assertSame('9.000000', $user->fresh()->balance);
        $this->assertSame(1, $user->plans()->count());
    }

    public function test_purchase_rejected_when_plan_sold_out(): void
    {
        $plan = $this->makePaidPlan(['price_usd' => 1.00, 'stock' => 0]);
        $user = User::factory()->create(['balance' => 10]);

        $this->purchase($user, $plan)->assertSessionHasErrors('plan');

        $this->assertSame(0, $plan->fresh()->stock);
        $this->assertSame('10.000000', $user->fresh()->balance);
        $this->assertSame(0, $user->plans()->count());
        $this->assertTrue($plan->is_sold_out);
    }

    public function test_plans_page_shows_stock_and_sold_out_state(): void
    {
        // Deterministik terlepas dari APP_LOCALE di .env.
        app()->setLocale('id');

        $this->makePaidPlan(['name' => 'Plan Tersedia', 'slug' => 'tersedia', 'stock' => 4]);
        $this->makePaidPlan(['name' => 'Plan Habis', 'slug' => 'habis', 'stock' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('plans'))
            ->assertOk()
            ->assertSee('Sisa 4')
            ->assertSee('Habis')
            ->assertSee('disabled'); // tombol beli nonaktif untuk plan habis
    }

    public function test_user_plan_usage_bar_attributes(): void
    {
        $plan = $this->makePaidPlan();
        $user = User::factory()->create(['balance' => 10]);
        $this->purchase($user, $plan);
        $userPlan = $user->plans()->firstOrFail();

        $this->assertSame($plan->total_tokens, $userPlan->quota_tokens);
        $this->assertSame(0, $userPlan->usage_percent);
        $this->assertSame(100, $userPlan->remaining_percent);

        // Simulasi pemakaian oleh gateway: bar mengecil dari 100 ke 0.
        $userPlan->update(['tokens_remaining' => 7500000]);
        $this->assertSame(7500000, $userPlan->fresh()->tokens_used);
        $this->assertSame(50, $userPlan->fresh()->usage_percent);
        $this->assertSame(50, $userPlan->fresh()->remaining_percent);

        // Kuota habis → bar kosong (0% tersisa).
        $userPlan->update(['tokens_remaining' => 0, 'status' => 'consumed']);
        $this->assertSame(100, $userPlan->fresh()->usage_percent);
        $this->assertSame(0, $userPlan->fresh()->remaining_percent);
    }

    public function test_free_plan_usage_bar_attributes(): void
    {
        $this->makeFreePlan();
        $user = User::factory()->create();
        $userPlan = Plan::grantFreePlan($user);

        $this->assertSame(7000000, $userPlan->quota_tokens);

        $userPlan->update(['daily_tokens_used' => 3500000, 'daily_reset_date' => now()->toDateString()]);
        $this->assertSame(50, $userPlan->fresh()->usage_percent);
        $this->assertSame(50, $userPlan->fresh()->remaining_percent);
    }

    public function test_sidebar_shows_active_plans(): void
    {
        $this->makeFreePlan();
        $paid = $this->makePaidPlan(['name' => 'Plan Premium', 'slug' => 'premium']);
        $user = User::factory()->create(['balance' => 10]);
        Plan::grantFreePlan($user);
        $this->purchase($user, $paid);

        // Sidebar (layout) tampil di semua halaman autentikasi.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('plan-mini', false)
            ->assertSee('Free Harian')
            ->assertSee('Plan Premium')
            ->assertSee('7M') // sisa kuota plan tampil di sidebar
            ->assertSee('plan-mini-bar', false); // progress bar ikut ter-render

        // Bar memakai remaining_percent (mengecil dari 100%).
        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('width:100%', false);
    }

    public function test_admin_can_create_plan_with_models(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $modelA = AiModel::create(['public_name' => 'azkia/a', 'upstream_name' => 'a', 'type' => 'chat', 'is_active' => true]);
        $modelB = AiModel::create(['public_name' => 'azkia/b', 'upstream_name' => 'b', 'type' => 'chat', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan Model Terbatas',
            'total_tokens' => 10000000,
            'duration_hours' => 24,
            'price_usd' => 1.00,
            'is_active' => 1,
            'model_ids' => [$modelA->id, $modelB->id],
        ])->assertRedirect();

        $plan = Plan::where('name', 'Plan Model Terbatas')->firstOrFail();
        $this->assertSame(2, $plan->models()->count());
        $this->assertTrue($plan->models->contains('id', $modelA->id));
        $this->assertTrue($plan->models->contains('id', $modelB->id));
    }

    public function test_admin_plan_without_models_covers_all_models(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Plan Semua Model',
            'total_tokens' => 10000000,
            'duration_hours' => 24,
            'price_usd' => 1.00,
            'is_active' => 1,
        ])->assertRedirect();

        $plan = Plan::where('name', 'Plan Semua Model')->firstOrFail();
        $this->assertSame(0, $plan->models()->count());
    }

    public function test_admin_free_plan_respects_model_selection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create(['public_name' => 'azkia/x', 'upstream_name' => 'x', 'type' => 'chat', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'Free Terbatas',
            'total_tokens' => 10000000,
            'duration_hours' => 24,
            'price_usd' => 1.00,
            'resets_daily' => 1,
            'is_active' => 1,
            'model_ids' => [$model->id],
        ])->assertRedirect();

        $plan = Plan::where('name', 'Free Terbatas')->firstOrFail();
        $this->assertTrue($plan->resets_daily);
        // Plan gratis kini bisa dibatasi model tertentu seperti plan berbayar.
        $this->assertSame(1, $plan->models()->count());
        $this->assertTrue($plan->models->contains('id', $model->id));
    }

    public function test_admin_can_update_plan_models(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $modelA = AiModel::create(['public_name' => 'azkia/a', 'upstream_name' => 'a', 'type' => 'chat', 'is_active' => true]);
        $modelB = AiModel::create(['public_name' => 'azkia/b', 'upstream_name' => 'b', 'type' => 'chat', 'is_active' => true]);
        $plan = $this->makePaidPlan();
        $plan->models()->sync([$modelA->id]);

        $this->actingAs($admin)->patch(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'total_tokens' => $plan->total_tokens,
            'duration_hours' => $plan->duration_hours,
            'price_usd' => $plan->price_usd,
            'is_active' => 1,
            'model_ids' => [$modelB->id],
        ])->assertRedirect();

        $this->assertSame(1, $plan->fresh()->models()->count());
        $this->assertTrue($plan->fresh()->models->contains('id', $modelB->id));
        $this->assertFalse($plan->fresh()->models->contains('id', $modelA->id));
    }

    public function test_user_plans_page_shows_plan_models(): void
    {
        $this->makePaidPlan(['name' => 'Plan Mini', 'slug' => 'mini'])->models()->sync([
            AiModel::create(['public_name' => 'azkia/modelX', 'upstream_name' => 'x', 'type' => 'chat', 'is_active' => true])->id,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('plans'))
            ->assertOk()
            ->assertSee('azkia/modelX');
    }

    public function test_admin_can_toggle_plan(): void
    {
        $plan = $this->makePaidPlan();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.plans.toggle', $plan))->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($admin)->patch(route('admin.plans.toggle', $plan))->assertRedirect();
        $this->assertTrue($plan->fresh()->is_active);
    }

    /**
     * Menghapus plan (soft delete) tidak boleh menghilangkan kuota yang sudah
     * dibeli: user_plans tetap utuh & aktif sampai kedaluwarsa, dan riwayat
     * tetap menampilkan nama plan.
     */
    public function test_admin_delete_plan_keeps_user_plans_usable(): void
    {
        $plan = $this->makePaidPlan();
        $user = User::factory()->create(['balance' => 10]);
        $userPlan = UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'tokens_remaining' => $plan->total_tokens,
            'daily_limit_tokens' => null,
            'daily_tokens_used' => 0,
            'expires_at' => now()->addHours(24),
            'purchased_at' => now(),
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))->assertRedirect();

        // Plan ter-soft-delete: tidak tampil lagi untuk pembelian.
        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
        $this->assertSame(0, Plan::where('id', $plan->id)->count());

        // Kuota user tetap utuh & aktif sampai kedaluwarsa.
        $this->assertSame(1, UserPlan::where('user_id', $user->id)->count());
        $this->assertSame('active', $userPlan->fresh()->status);
        $this->assertFalse($userPlan->fresh()->is_expired);
        $this->assertSame($plan->total_tokens, $userPlan->fresh()->tokens_remaining);

        // Riwayat user tetap menampilkan nama plan (relation withTrashed).
        $this->assertSame($plan->name, $userPlan->fresh()->plan->name);
    }

    /**
     * Latent bug: plan berbayar tanpa duration_hours (null) dibeli →
     * purchasePlan memakai now()->addHours(null) = +0 jam sehingga
     * expires_at = waktu pembelian → plan langsung kedaluwarsa & tak terpakai.
     */
    public function test_paid_plan_without_duration_does_not_expire_immediately(): void
    {
        $plan = $this->makePaidPlan(['duration_hours' => null]);
        $user = User::factory()->create(['balance' => 10]);

        $this->purchase($user, $plan)->assertRedirect(route('plans'));

        $userPlan = $user->plans()->firstOrFail();
        $this->assertNull($userPlan->expires_at, 'Plan tanpa durasi seharusnya tidak kedaluwarsa');
        $this->assertFalse($userPlan->is_expired);

        // Notifikasi tidak menampilkan tanggal kedaluwarsa yang menyesatkan.
        $inbox = InboxMessage::where('user_id', $user->id)->firstOrFail();
        $this->assertStringContainsString('tanpa batas waktu', $inbox->body);
        $this->assertStringNotContainsString('WIB', $inbox->body);
    }
}

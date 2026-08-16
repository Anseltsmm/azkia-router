<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\PricingRule;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPricingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_DOMAIN = 'http://admin.azkia.cloud';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['IDR' => 16250]], 200),
            '127.0.0.1:8001/health/models*' => Http::response([
                'checked_at' => now()->toIso8601String(),
                'data' => [
                    ['model' => 'azkia/tes', 'upstream' => 'gpt-4o-mini', 'reachable' => true, 'status_code' => 200, 'latency_ms' => 223, 'has_pricing' => true],
                ],
            ], 200),
            '127.0.0.1:8001/*' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    public function test_login_pages_show_domain_placeholder(): void
    {
        // Login admin (admin.azkia.cloud): placeholder & judul khusus admin, tanpa link register.
        $this->get(self::ADMIN_DOMAIN.'/login')
            ->assertOk()
            ->assertSee('Admin Login')
            ->assertSee('admin@azkia.cloud')
            ->assertDontSee('Belum punya akun?');

        // Login user (domain default): placeholder generik, tanpa link register
        // (registrasi email/password dinonaktifkan — akun baru lewat Google login).
        // Pakai host eksplisit agar tidak ikut terarah ke admin.azkia.cloud.
        $this->get('http://localhost/login')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('nama@email.com')
            ->assertDontSee('Belum punya akun?') // link register sudah dihapus
            ->assertSee('Baru pertama kali?');
    }

    public function test_all_admin_pages_load(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);

        foreach (['admin.index', 'admin.providers', 'admin.models', 'admin.pricing', 'admin.status', 'admin.keys', 'admin.users'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_status_page_renders_with_usage_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'capabilities' => ['chat'],
        ]);
        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/tes',
            'endpoint' => '/chat/completions',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost' => 0.0005,
            'status_code' => 200,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/tes',
            'endpoint' => '/chat/completions',
            'input_tokens' => 5,
            'output_tokens' => 5,
            'cost' => 0.0001,
            'status_code' => 429,
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.status'))
            ->assertOk()
            ->assertSee('azkia/tes')
            ->assertSee('errors')
            ->assertSee('223ms');   // latency ping live tetap tampil
    }

    public function test_status_page_loads_with_model_without_usage(): void
    {
        // Model baru yang belum pernah dipakai (tanpa usage_logs) tidak boleh membuat 500.
        $admin = User::factory()->create(['is_admin' => true]);
        AiModel::create([
            'public_name' => 'azkia/baru',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'capabilities' => ['chat'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.status'))
            ->assertOk()
            ->assertSee('azkia/baru');
    }

    public function test_admin_can_add_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/models', [
                'public_name' => 'azkia/tes',
                'upstream_name' => 'gpt-4o-mini',
                'capabilities' => ['chat'],
                'context_window' => 128000,
            ])
            ->assertRedirect();

        $model = AiModel::where('public_name', 'azkia/tes')->first();
        $this->assertNotNull($model);
        $this->assertSame('chat', $model->type);
        $this->assertSame(['chat'], $model->capabilities);
    }

    private function fakePng(string $name): UploadedFile
    {
        // PNG 1x1 valid (base64) — tidak butuh ekstensi GD yang tidak terpasang di server.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_admin_can_upload_svg_icon(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Storage::fake('public');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24" rx="4" fill="#2563eb"/></svg>';

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/models', [
                'public_name' => 'azkia/ikon-svg',
                'upstream_name' => 'gpt-4o-mini',
                'capabilities' => ['chat'],
                'icon' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
            ])
            ->assertRedirect();

        $model = AiModel::where('public_name', 'azkia/ikon-svg')->first();
        $this->assertNotNull($model);
        $this->assertNotNull($model->icon_path);
        $this->assertStringEndsWith('.svg', $model->icon_path);
    }

    public function test_admin_can_upload_model_icon(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Storage::fake('public');

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/models', [
                'public_name' => 'azkia/ikon',
                'upstream_name' => 'gpt-4o-mini',
                'capabilities' => ['chat'],
                'icon' => $this->fakePng('logo.png'),
            ])
            ->assertRedirect();

        $model = AiModel::where('public_name', 'azkia/ikon')->first();
        $this->assertNotNull($model);
        $this->assertNotNull($model->icon_path);

        Storage::disk('public')->assertExists($model->icon_path);
    }

    public function test_admin_can_update_and_remove_model_icon(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Storage::fake('public');

        $model = AiModel::create([
            'public_name' => 'azkia/ikon',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);

        // Ganti ikon
        $this->actingAs($admin)
            ->patch(self::ADMIN_DOMAIN.'/models/'.$model->id, [
                'public_name' => 'azkia/ikon',
                'upstream_name' => 'gpt-4o-mini',
                'icon' => $this->fakePng('baru.png'),
            ])
            ->assertRedirect(route('admin.models'));

        $model->refresh();
        $this->assertNotNull($model->icon_path);

        // Hapus ikon
        $this->actingAs($admin)
            ->patch(self::ADMIN_DOMAIN.'/models/'.$model->id, [
                'public_name' => 'azkia/ikon',
                'upstream_name' => 'gpt-4o-mini',
                'remove_icon' => '1',
            ])
            ->assertRedirect(route('admin.models'));

        $model->refresh();
        $this->assertNull($model->icon_path);
    }

    public function test_model_icon_shows_on_user_models_page(): void
    {
        $user = User::factory()->create();
        $model = AiModel::create([
            'public_name' => 'azkia/ikon',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'icon_path' => 'model-icons/test.png',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('models'))
            ->assertOk()
            ->assertSee('azkia/ikon')
            ->assertSee('model-icons/test.png');
    }

    public function test_admin_can_edit_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/lama',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'capabilities' => ['chat'],
        ]);

        // Halaman edit harus dimuat tanpa error dan menampilkan data model.
        $this->actingAs($admin)
            ->get(self::ADMIN_DOMAIN.'/models/'.$model->id.'/edit')
            ->assertOk()
            ->assertSee('azkia/lama');

        $this->actingAs($admin)
            ->patch(self::ADMIN_DOMAIN.'/models/'.$model->id, [
                'provider_id' => null,
                'public_name' => 'azkia/baru',
                'upstream_name' => 'gpt-4o',
                'capabilities' => ['chat', 'tool'],
                'context_window' => 200000,
            ])
            ->assertRedirect(route('admin.models'));

        $model->refresh();
        $this->assertSame('azkia/baru', $model->public_name);
        $this->assertSame('gpt-4o', $model->upstream_name);
        $this->assertSame('chat', $model->type);
        $this->assertSame(['chat', 'tool'], $model->capabilities);
        $this->assertSame(200000, (int) $model->context_window);
    }

    public function test_admin_can_update_model_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/mati',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(self::ADMIN_DOMAIN.'/models/'.$model->id, [
                'provider_id' => null,
                'public_name' => 'azkia/mati',
                'upstream_name' => 'gpt-4o-mini',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.models'));

        $model->refresh();
        $this->assertTrue($model->is_active);
    }

    public function test_admin_can_delete_model_and_cascades_pricing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/hapus',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);
        $rule = PricingRule::create([
            'ai_model_id' => $model->id,
            'input_per_million' => 0.18,
            'output_per_million' => 0.32,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(self::ADMIN_DOMAIN.'/models/'.$model->id)
            ->assertRedirect(route('admin.models'));

        $this->assertDatabaseMissing('ai_models', ['id' => $model->id]);
        $this->assertDatabaseMissing('pricing_rules', ['id' => $rule->id]);
    }

    public function test_edit_model_page_shows_pricing_and_promo(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/promo',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);
        PricingRule::create([
            'ai_model_id' => $model->id,
            'input_per_million' => 0.10,
            'output_per_million' => 0.20,
            'currency' => 'USD',
            'is_active' => true,
            'is_promo' => true,
        ]);

        $this->actingAs($admin)
            ->get(self::ADMIN_DOMAIN.'/models/'.$model->id.'/edit')
            ->assertOk()
            ->assertSee('azkia/promo')
            ->assertSee('Pricing & Promo', false)
            ->assertSee('PROMO');
    }

    public function test_admin_can_add_multimodal_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/models', [
                'public_name' => 'azkia/multimodal',
                'upstream_name' => 'gpt-4o',
                'capabilities' => ['chat', 'embedding', 'tool'],
            ])
            ->assertRedirect();

        $model = AiModel::where('public_name', 'azkia/multimodal')->first();
        $this->assertNotNull($model);
        // type diturunkan dari kemampuan pertama
        $this->assertSame('chat', $model->type);
        $this->assertSame(['chat', 'embedding', 'tool'], $model->capabilities);
    }

    public function test_admin_can_add_pricing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/pricing', [
                'ai_model_id' => $model->id,
                'input_per_million' => 0.18,
                'output_per_million' => 0.32,
                'cache_read_input_per_million' => 0.09,
                'cache_write_per_million' => 0.18,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pricing_rules', [
            'ai_model_id' => $model->id,
            'input_per_million' => 0.18,
            'output_per_million' => 0.32,
            'cache_read_input_per_million' => 0.09,
            'cache_write_per_million' => 0.18,
            'currency' => 'USD',
        ]);
    }

    public function test_admin_can_add_pricing_with_promo(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/pricing', [
                'ai_model_id' => $model->id,
                'input_per_million' => 0.10,
                'output_per_million' => 0.20,
                'is_promo' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pricing_rules', [
            'ai_model_id' => $model->id,
            'input_per_million' => 0.10,
            'output_per_million' => 0.20,
            'is_promo' => true,
        ]);
    }

    public function test_pricing_without_promo_flag_defaults_to_false(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);

        $this->actingAs($admin)
            ->post(self::ADMIN_DOMAIN.'/pricing', [
                'ai_model_id' => $model->id,
                'input_per_million' => 0.18,
                'output_per_million' => 0.32,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pricing_rules', [
            'ai_model_id' => $model->id,
            'is_promo' => false,
        ]);
    }

    public function test_promo_label_shows_on_models_page(): void
    {
        $user = User::factory()->create();
        $model = AiModel::create([
            'public_name' => 'azkia/promo',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'is_active' => true,
        ]);
        PricingRule::create([
            'ai_model_id' => $model->id,
            'input_per_million' => 0.10,
            'output_per_million' => 0.20,
            'currency' => 'USD',
            'is_active' => true,
            'is_promo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('models'))
            ->assertOk()
            ->assertSee('azkia/promo')
            ->assertSee('Promo');
    }

    public function test_non_promo_model_has_no_label_on_models_page(): void
    {
        $user = User::factory()->create();
        $model = AiModel::create([
            'public_name' => 'azkia/plain',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'is_active' => true,
        ]);
        PricingRule::create([
            'ai_model_id' => $model->id,
            'input_per_million' => 0.18,
            'output_per_million' => 0.32,
            'currency' => 'USD',
            'is_active' => true,
            'is_promo' => false,
        ]);

        $this->actingAs($user)
            ->get(route('models'))
            ->assertOk()
            ->assertSee('azkia/plain')
            ->assertDontSee('Promo');
    }

    public function test_pricing_rejects_negative_cache_price(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);

        $this->actingAs($admin)
            ->from(self::ADMIN_DOMAIN.'/')
            ->post(self::ADMIN_DOMAIN.'/pricing', [
                'ai_model_id' => $model->id,
                'input_per_million' => 0.18,
                'output_per_million' => 0.32,
                'cache_read_input_per_million' => -0.05,
            ])
            ->assertSessionHasErrors('cache_read_input_per_million');

        $this->assertSame(0, PricingRule::count());
    }

    public function test_pricing_rejects_negative_price(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
        ]);

        $this->actingAs($admin)
            ->from(self::ADMIN_DOMAIN.'/')
            ->post(self::ADMIN_DOMAIN.'/pricing', [
                'ai_model_id' => $model->id,
                'input_per_million' => -1,
                'output_per_million' => 0.32,
            ])
            ->assertSessionHasErrors('input_per_million');

        $this->assertSame(0, PricingRule::count());
    }
}

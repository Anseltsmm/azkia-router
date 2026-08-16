<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\PricingRule;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kurs realtime & health gateway ditentukan agar tampilan deterministik tanpa koneksi eksternal.
        // Catatan: stub dicocokkan berurutan — /health/models harus sebelum catch-all.
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['IDR' => 16250]], 200),
            '127.0.0.1:8001/health/models*' => Http::response([
                'checked_at' => now()->toIso8601String(),
                'data' => [
                    ['model' => 'azkia/tes', 'upstream' => 'gpt-4o-mini', 'reachable' => true, 'status_code' => 200, 'latency_ms' => 223, 'has_pricing' => true],
                ],
            ], 200),
            '127.0.0.1:8001/*' => Http::response([
                'status' => 'ok',
                'service' => 'azkia-gateway',
                'version' => '0.2.0',
                'uptime_seconds' => 3661,
                'database' => 'ok',
                'redis' => 'ok',
                'active_models' => 3,
                'timestamp' => '2026-08-13T00:00:00+00:00',
            ], 200),
        ]);
    }

    public function test_billing_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('billing'))
            ->assertOk();
    }

    public function test_all_authenticated_pages_load(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        foreach (['dashboard', 'keys', 'usage', 'billing', 'api-health', 'models', 'status', 'leaderboard', 'docs', 'settings'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_leaderboard_ranks_models_by_total_tokens(): void
    {
        $user = User::factory()->create();
        $manyInput = AiModel::create([
            'public_name' => 'azkia/banyak-input',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'is_active' => true,
        ]);
        $manyOutput = AiModel::create([
            'public_name' => 'azkia/banyak-output',
            'upstream_name' => 'gpt-4o-mini',
            'type' => 'chat',
            'is_active' => true,
        ]);
        PricingRule::create([
            'ai_model_id' => $manyOutput->id,
            'input_per_million' => 0.18,
            'output_per_million' => 0.32,
            'currency' => 'USD',
            'is_active' => true,
            'is_promo' => true,
        ]);

        // banyak-input: total 5100 token tapi OUTPUT hanya 100.
        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/banyak-input',
            'endpoint' => '/chat/completions',
            'input_tokens' => 5000,
            'output_tokens' => 100,
            'cost' => 0.001,
            'status_code' => 200,
        ]);
        // banyak-output: total 5010 token tapi OUTPUT 5000.
        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/banyak-output',
            'endpoint' => '/chat/completions',
            'input_tokens' => 10,
            'output_tokens' => 5000,
            'cost' => 0.001,
            'status_code' => 200,
        ]);

        $this->actingAs($user)
            ->get(route('leaderboard'))
            ->assertOk()
            ->assertSee('Leaderboard Model')
            ->assertSee('azkia/banyak-output')
            ->assertSee('azkia/banyak-input')
            ->assertDontSee('Promo')   // tanpa badge promo di leaderboard
            // Ranking mengikuti TOTAL token (input + output).
            ->assertSeeInOrder(['azkia/banyak-input', 'azkia/banyak-output']);
    }

    public function test_status_page_shows_model_status(): void
    {
        $user = User::factory()->create();
        $model = AiModel::create([
            'public_name' => 'azkia/tes',
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
        ]);
        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/tes',
            'endpoint' => '/chat/completions',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost' => 0.0005,
            'status_code' => 200,
        ]);

        $this->actingAs($user)
            ->get(route('status'))
            ->assertOk()
            ->assertSee('azkia/tes')
            ->assertSee('Operational')
            ->assertSee('223ms')            // latency ping realtime tampil
            ->assertSee('5 jam terakhir')   // timeline 5 jam terakhir tampil
            ->assertSee('aria-label="Riwayat 5 jam terakhir"', false);
    }

    public function test_settings_page_loads_and_updates_profile(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);

        $this->actingAs($user)
            ->withCookie('locale', 'id')
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertSee('Profil');

        // Update profil
        $this->actingAs($user)
            ->patch(route('settings.profile'), ['name' => 'Budi Baru', 'email' => 'budi.baru@example.com'])
            ->assertSessionHas('success')
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Budi Baru', 'email' => 'budi.baru@example.com']);
    }

    public function test_usage_page_shows_charts(): void
    {
        $user = User::factory()->create();

        UsageLog::create([
            'user_id' => $user->id,
            'model' => 'azkia/tes',
            'endpoint' => '/chat/completions',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'cost' => 0.001,
            'status_code' => 200,
        ]);

        $this->actingAs($user)
            ->get(route('usage'))
            ->assertOk()
            ->assertSee('Aktivitas per Hari')
            ->assertSee('Pemakaian per Model')
            ->assertSee('azkia/tes')
            ->assertSee('Token')
            ->assertSee('Biaya');
    }
}

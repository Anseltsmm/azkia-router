<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\BillingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_monitoring_requires_admin(): void
    {
        $this->get('http://admin.azkia.cloud/billing-monitoring')->assertRedirect('http://admin.azkia.cloud/login');
        $this->actingAs(User::factory()->create())->get(route('admin.billing-monitoring.index'))->assertForbidden();
    }

    public function test_admin_can_filter_and_view_redacted_detail(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['email' => 'billing-target@example.test']);
        $key = ApiKey::create(['user_id' => $user->id, 'name' => 'Production', 'prefix' => 'azk_test', 'key_hash' => hash('sha256', 'secret'), 'is_active' => true]);
        $event = BillingEvent::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'api_key_id' => $key->id, 'model' => 'safe-model', 'endpoint' => '/chat/completions', 'status' => 'pending', 'reserved_cost' => '1.000000', 'reserved_tokens' => 100, 'payload' => ['model' => 'safe-model', 'cost' => '1.000000', 'authorization' => 'Bearer secret', 'request' => ['prompt' => 'private']]]);
        $this->actingAs($admin)->get(route('admin.billing-monitoring.index', ['search' => 'billing-target', 'model' => 'safe-model', 'status' => 'pending']))->assertOk()->assertSee($event->id);
        $this->actingAs($admin)->get(route('admin.billing-monitoring.show', $event))->assertOk()->assertSee('safe-model')->assertDontSee('Bearer secret')->assertDontSee('private');
    }
}

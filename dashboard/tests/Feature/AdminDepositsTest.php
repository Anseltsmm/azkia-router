<?php

namespace Tests\Feature;

use App\Models\FinancialAuditEvent;
use App\Models\InboxMessage;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDepositsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposits_require_admin_authentication(): void
    {
        $this->get('http://admin.azkia.cloud/deposits')->assertRedirect('http://admin.azkia.cloud/login');
        $this->actingAs(User::factory()->create())->get(route('admin.deposits.index'))->assertForbidden();
    }

    public function test_admin_can_list_and_filter_deposits(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->order(User::factory()->create(['name' => 'Deposit Target']), '=CSV-REF');
        $this->actingAs($admin)->get(route('admin.deposits.index', ['search' => 'Deposit Target', 'status' => 'UNPAID']))->assertOk()->assertSee($order->merchant_ref);
    }

    public function test_reconcile_is_idempotent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->order($user);
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => ['reference' => $order->tripay_reference, 'merchant_ref' => $order->merchant_ref, 'total_amount' => $order->total_amount, 'status' => 'PAID']])]);
        $this->actingAs($admin)->post(route('admin.deposits.reconcile', $order));
        $this->actingAs($admin)->post(route('admin.deposits.reconcile', $order));
        $this->assertSame('10.000000', $user->fresh()->balance);
    }

    public function test_manual_credit_checks_password_and_idempotency(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('secret-pass')]);
        $user = User::factory()->create(['balance' => 0]);
        $key = (string) Str::uuid();
        $payload = ['user_id' => $user->id, 'amount' => '1.123456', 'reason' => 'Kompensasi layanan sah', 'idempotency_key' => $key, 'current_password' => 'wrong'];
        $this->actingAs($admin)->post(route('admin.deposits.manual-credit'), $payload)->assertSessionHasErrors('current_password');
        $payload['current_password'] = 'secret-pass';
        $this->actingAs($admin)->post(route('admin.deposits.manual-credit'), $payload);
        $this->actingAs($admin)->post(route('admin.deposits.manual-credit'), $payload);
        $this->assertSame('1.123456', $user->fresh()->balance);
        $this->assertSame(1, FinancialAuditEvent::where('idempotency_key', $key)->count());
        $this->assertSame(1, InboxMessage::where('dedupe_key', "deposit:manual:{$key}:credited")->count());
    }

    public function test_manual_credit_failure_does_not_notify_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('secret-pass')]);
        $user = User::factory()->create(['balance' => 0]);

        $this->actingAs($admin)->post(route('admin.deposits.manual-credit'), [
            'user_id' => $user->id,
            'amount' => '1.000000',
            'reason' => 'Penyesuaian saldo pengguna',
            'idempotency_key' => (string) Str::uuid(),
            'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame(0, InboxMessage::count());
    }

    public function test_export_sanitizes_formula_cells(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->order(User::factory()->create(['name' => '=CMD']), '=CSV-REF');
        $response = $this->actingAs($admin)->get(route('admin.deposits.export'));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("'=CSV-REF", $response->streamedContent());
        $this->assertStringContainsString("'=CMD", $response->streamedContent());
    }

    private function order(User $user, string $merchantRef = 'AZK-TEST'): PaymentOrder
    {
        return PaymentOrder::create(['user_id' => $user->id, 'merchant_ref' => $merchantRef, 'tripay_reference' => 'TRIPAY-'.Str::random(8), 'payment_method' => 'BRIVA', 'amount_idr' => 100000, 'credit_usd' => '10.000000', 'exchange_rate' => 10000, 'total_amount' => 101000, 'status' => 'UNPAID']);
    }
}

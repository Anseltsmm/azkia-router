<?php

namespace Tests\Feature;

use App\Models\InboxMessage;
use App\Models\PaymentOrder;
use App\Models\PaymentSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TripayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TripayReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentSetting::create([
            'provider' => 'tripay',
            'mode' => 'sandbox',
            'api_key_encrypted' => encrypt('api-key'),
            'private_key_encrypted' => encrypt('private-key'),
            'merchant_code_encrypted' => encrypt('merchant-code'),
            'minimum_topup' => 10000,
            'expiry_hours' => 24,
            'is_active' => true,
        ]);
    }

    public function test_reconciliation_credits_paid_order_once(): void
    {
        [$user, $order] = $this->order();
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);
        app(TripayService::class)->reconcile($order->fresh());

        $this->assertSame('10.000000', $user->fresh()->balance);
        $this->assertNotNull($order->fresh()->credited_at);
        $this->assertSame(1, Transaction::where('payment_order_id', $order->id)->count());
        $this->assertSame(1, InboxMessage::where('dedupe_key', "deposit:tripay:{$order->id}:credited")->count());
    }

    public function test_reconciliation_does_not_notify_for_nonpaid_or_failed_processing(): void
    {
        [, $order] = $this->order();
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => array_merge($this->paidData($order), ['status' => 'UNPAID'])])]);

        app(TripayService::class)->reconcile($order);

        $this->assertSame(0, InboxMessage::count());

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => array_merge($this->paidData($order), ['total_amount' => 1])])]);

        try {
            app(TripayService::class)->reconcile($order->fresh());
        } catch (HttpException) {
        }

        $this->assertSame(0, InboxMessage::count());
    }

    public function test_callback_and_reconciliation_use_same_status_rules(): void
    {
        [, $order] = $this->order();
        $service = app(TripayService::class);
        $service->processCallback($this->paidData($order));
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => array_merge($this->paidData($order), ['status' => 'EXPIRED'])])]);

        $service->reconcile($order->fresh());

        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame(1, Transaction::where('payment_order_id', $order->id)->count());
    }

    public function test_reconciliation_rejects_mismatched_amount(): void
    {
        [, $order] = $this->order();
        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => array_merge($this->paidData($order), ['total_amount' => 100001])])]);

        $this->expectExceptionMessage('Amount mismatch');

        app(TripayService::class)->reconcile($order);
    }

    public function test_command_continues_after_an_order_error_and_prints_summary(): void
    {
        [, $first] = $this->order('AZK-FIRST', 'TRIPAY-FIRST');
        [, $second] = $this->order('AZK-SECOND', 'TRIPAY-SECOND');
        Http::fake(function ($request) use ($first, $second) {
            $reference = $request->data()['reference'];

            if ($reference === $first->tripay_reference) {
                return Http::response(['success' => false, 'message' => 'Unavailable']);
            }

            return Http::response(['success' => true, 'data' => $this->paidData($second)]);
        });

        $this->artisan('tripay:reconcile', ['--limit' => 2, '--older-than' => 0])
            ->expectsOutputToContain('processed=2, succeeded=1, failed=1')
            ->assertFailed();

        $this->assertSame('PAID', $second->fresh()->status);
    }

    private function order(string $merchantRef = 'AZK-TEST', string $reference = 'TRIPAY-TEST'): array
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'merchant_ref' => $merchantRef,
            'tripay_reference' => $reference,
            'payment_method' => 'BRIVA',
            'amount_idr' => 100000,
            'credit_usd' => 10,
            'exchange_rate' => 10000,
            'total_amount' => 101000,
            'status' => 'UNPAID',
        ]);

        return [$user, $order];
    }

    private function paidData(PaymentOrder $order): array
    {
        return [
            'reference' => $order->tripay_reference,
            'merchant_ref' => $order->merchant_ref,
            'total_amount' => $order->total_amount,
            'status' => 'PAID',
            'paid_at' => now()->timestamp,
        ];
    }
}

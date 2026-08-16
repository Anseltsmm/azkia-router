<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\FinancialAuditEvent;
use App\Models\PaymentOrder;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\TripayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopupPromoFeatureTest extends TestCase
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

    public function test_admin_can_update_topup_promo_tier_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.event.update-topup'), [
                'enabled' => '1',
                'type' => 'tier',
                'tiers' => [
                    ['min_idr' => 50000, 'bonus_idr' => 5000],
                    ['min_idr' => 100000, 'bonus_idr' => 15000],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertSame('1', AppSetting::get('topup_promo.enabled'));
        $this->assertSame('tier', AppSetting::get('topup_promo.type'));
        $this->assertSame(
            [['min_idr' => 50000, 'bonus_idr' => 5000], ['min_idr' => 100000, 'bonus_idr' => 15000]],
            json_decode((string) AppSetting::get('topup_promo.tiers'), true)
        );

        $audit = FinancialAuditEvent::where('action', 'event_topup_settings')->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->actor_id);
    }

    public function test_admin_can_update_topup_promo_percent_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.event.update-topup'), [
                'enabled' => '1',
                'type' => 'percent',
                'percent' => '5',
            ])
            ->assertSessionHas('success');

        $this->assertSame('percent', AppSetting::get('topup_promo.type'));
        $this->assertSame('5.00', AppSetting::get('topup_promo.percent'));
    }

    public function test_store_creates_order_with_tier_bonus(): void
    {
        AppSetting::set('topup_promo.enabled', '1');
        AppSetting::set('topup_promo.type', 'tier');
        AppSetting::set('topup_promo.tiers', json_encode([['min_idr' => 50000, 'bonus_idr' => 5000]]));

        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['IDR' => 16000]], 200),
            'tripay.co.id/*' => Http::sequence()
                ->push(['success' => true, 'data' => [
                    ['code' => 'BRIVA', 'name' => 'BRIVA', 'group' => 'Virtual Account', 'icon_url' => '', 'minimum_amount' => 10000, 'maximum_amount' => 100000000, 'active' => true],
                ]])
                ->push(['success' => true, 'data' => [
                    'reference' => 'REF-BONUS-1', 'payment_name' => 'BRIVA', 'fee_customer' => 1000,
                    'total_amount' => 101000, 'status' => 'UNPAID', 'amount' => 100000, 'expired_time' => now()->addDay()->timestamp,
                ]]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('payments.store'), ['amount' => 100000, 'method' => 'BRIVA', 'customer_phone' => '081234567890'])
            ->assertRedirect();

        $order = PaymentOrder::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(5000, (int) $order->bonus_idr);
        $this->assertSame('0.312500', (string) $order->bonus_usd); // 5000 / 16000
    }

    public function test_tier_bonus_credited_on_paid_order(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'merchant_ref' => 'AZK-TOPUP-1',
            'tripay_reference' => 'TRIPAY-TOPUP-1',
            'payment_method' => 'BRIVA',
            'amount_idr' => 100000,
            'bonus_idr' => 15000,
            'bonus_usd' => '0.923077',
            'credit_usd' => '6.153846',
            'exchange_rate' => 16250,
            'total_amount' => 101000,
            'status' => 'UNPAID',
        ]);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);

        // 6.153846 + 0.923077 = 7.076923
        $this->assertSame('7.076923', $user->fresh()->balance);

        $audit = FinancialAuditEvent::where('action', 'topup_bonus')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->target_user_id);
        $this->assertSame($order->id, $audit->payment_order_id);
        $this->assertSame('0.923077', $audit->amount);
        $this->assertSame(15000, $audit->metadata['bonus_idr']);

        // Reconcile ulang tidak dobel kredit.
        app(TripayService::class)->reconcile($order->fresh());
        $this->assertSame('7.076923', $user->fresh()->balance);
        $this->assertSame(1, FinancialAuditEvent::where('action', 'topup_bonus')->count());
    }

    public function test_no_bonus_when_promo_disabled(): void
    {
        AppSetting::set('topup_promo.enabled', '0');

        $user = User::factory()->create(['balance' => 0]);
        $order = PaymentOrder::create([
            'user_id' => $user->id,
            'merchant_ref' => 'AZK-TOPUP-2',
            'tripay_reference' => 'TRIPAY-TOPUP-2',
            'payment_method' => 'BRIVA',
            'amount_idr' => 100000,
            'bonus_idr' => 0,
            'bonus_usd' => '0',
            'credit_usd' => '6.153846',
            'exchange_rate' => 16250,
            'total_amount' => 101000,
            'status' => 'UNPAID',
        ]);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);

        $this->assertSame('6.153846', $user->fresh()->balance);
        $this->assertSame(0, FinancialAuditEvent::where('action', 'topup_bonus')->count());
    }

    public function test_calculate_bonus_respects_tier_minimums(): void
    {
        AppSetting::set('topup_promo.enabled', '1');
        AppSetting::set('topup_promo.type', 'tier');
        AppSetting::set('topup_promo.tiers', json_encode([
            ['min_idr' => 50000, 'bonus_idr' => 5000],
            ['min_idr' => 100000, 'bonus_idr' => 15000],
        ]));

        $service = app(\App\Services\TopupPromoService::class);
        $this->assertSame(0, $service->calculateBonusIdr(10000));       // di bawah jenjang
        $this->assertSame(5000, $service->calculateBonusIdr(50000));    // tepat di jenjang 1
        $this->assertSame(5000, $service->calculateBonusIdr(75000));    // jenjang 1
        $this->assertSame(15000, $service->calculateBonusIdr(100000));  // jenjang tertinggi

        // Mode persen
        AppSetting::set('topup_promo.type', 'percent');
        AppSetting::set('topup_promo.percent', '5');
        $this->assertSame(5000, $service->calculateBonusIdr(100000));
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

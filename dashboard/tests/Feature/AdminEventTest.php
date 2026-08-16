<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\PaymentOrder;
use App\Models\PaymentSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TripayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminEventTest extends TestCase
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

    public function test_event_page_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.event'))
            ->assertForbidden();
    }

    public function test_admin_can_view_and_update_event_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.event'))
            ->assertOk()
            ->assertSee('Event');

        $this->actingAs($admin)
            ->patch(route('admin.event.update'), [
                'enabled' => '1',
                'reward_usd' => '1.25',
                'min_topup_idr' => '100000',
            ])
            ->assertSessionHas('success');

        $this->assertSame('1', AppSetting::get('referral.enabled'));
        $this->assertSame('1.250000', AppSetting::get('referral.reward_usd'));
        $this->assertSame('100000', AppSetting::get('referral.min_topup_idr'));
    }

    public function test_referral_reward_uses_db_settings(): void
    {
        AppSetting::set('referral.reward_usd', '1.000000');
        AppSetting::set('referral.min_topup_idr', '100000');

        $referrer = User::factory()->create(['balance' => 0, 'referral_code' => 'REFER11']);
        $friend = User::factory()->create(['balance' => 0, 'referral_code' => 'FRIEND11', 'referred_by' => $referrer->id]);
        $order = $this->order($friend, 'AZK-EVT-1', 'TRIPAY-EVT-1', 100000);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);

        $this->assertSame('1.000000', $referrer->fresh()->balance);
    }

    public function test_referral_disabled_from_admin_prevents_reward(): void
    {
        AppSetting::set('referral.enabled', '0');

        $referrer = User::factory()->create(['balance' => 0, 'referral_code' => 'REFER12']);
        $friend = User::factory()->create(['balance' => 0, 'referral_code' => 'FRIEND12', 'referred_by' => $referrer->id]);
        $order = $this->order($friend, 'AZK-EVT-2', 'TRIPAY-EVT-2', 100000);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);

        $this->assertSame('0.000000', $referrer->fresh()->balance);
        $this->assertSame(0, Transaction::where('type', 'referral_reward')->count());
    }

    private function order(User $user, string $merchantRef, string $reference, int $amountIdr): PaymentOrder
    {
        return PaymentOrder::create([
            'user_id' => $user->id,
            'merchant_ref' => $merchantRef,
            'tripay_reference' => $reference,
            'payment_method' => 'BRIVA',
            'amount_idr' => $amountIdr,
            'credit_usd' => $amountIdr / 10000,
            'exchange_rate' => 10000,
            'total_amount' => $amountIdr + 1000,
            'status' => 'UNPAID',
        ]);
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

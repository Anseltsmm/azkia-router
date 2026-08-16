<?php

namespace Tests\Feature;

use App\Models\FinancialAuditEvent;
use App\Models\PaymentOrder;
use App\Models\PaymentSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TripayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class ReferralFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'test-client-id']);

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

    public function test_google_registration_assigns_referral_code(): void
    {
        $this->mockGoogleUser('budi@example.com', 'g-1');

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertNotNull($user->referral_code);
        $this->assertNull($user->referred_by);
    }

    public function test_referral_code_from_session_applied_on_registration(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'ABCD1234']);
        $this->mockGoogleUser('teman@example.com', 'g-2');

        $this->withSession(['referral_code' => 'ABCD1234'])
            ->get(route('google.callback'));

        $friend = User::where('email', 'teman@example.com')->firstOrFail();
        $this->assertSame($referrer->id, $friend->referred_by);
        // Session referral terpakai & dibersihkan.
        $this->assertNull(session('referral_code'));
    }

    public function test_track_referral_middleware_captures_ref_param_for_guests(): void
    {
        User::factory()->create(['referral_code' => 'CODE1234']);

        $this->get('/?ref=CODE1234')->assertOk();

        $this->assertSame('CODE1234', session('referral_code'));
    }

    public function test_reward_credited_once_on_first_qualifying_topup(): void
    {
        $referrer = User::factory()->create(['balance' => 0, 'referral_code' => 'REFER01']);
        $friend = User::factory()->create(['balance' => 0, 'referral_code' => 'FRIEND01', 'referred_by' => $referrer->id]);
        $order = $this->order($friend, 'AZK-REF-1', 'TRIPAY-REF-1', 100000);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);
        app(TripayService::class)->reconcile($order->fresh());

        $this->assertSame('0.500000', $referrer->fresh()->balance);
        $this->assertSame(1, Transaction::where('type', 'referral_reward')->count());
        $this->assertNotNull($friend->fresh()->referral_rewarded_at);
        // Audit: reward referral tercatat, terhubung ke order & transaksi.
        $audit = FinancialAuditEvent::where('action', 'referral_reward')->get();
        $this->assertSame(1, $audit->count());
        $this->assertSame($referrer->id, $audit->first()->target_user_id);
        $this->assertSame($order->id, $audit->first()->payment_order_id);
        $this->assertNotNull($audit->first()->transaction_id);
        $this->assertSame('0.500000', $audit->first()->amount);
        $this->assertSame($friend->id, $audit->first()->metadata['referred_user_id']);
        // Reconcile ulang tidak menambah reward.
        $this->assertSame('0.500000', $referrer->fresh()->balance);
        $this->assertSame(1, Transaction::where('type', 'referral_reward')->count());
        $this->assertSame(1, FinancialAuditEvent::where('action', 'referral_reward')->count());
    }

    public function test_no_reward_below_minimum_topup(): void
    {
        $referrer = User::factory()->create(['balance' => 0, 'referral_code' => 'REFER02']);
        $friend = User::factory()->create(['balance' => 0, 'referral_code' => 'FRIEND02', 'referred_by' => $referrer->id]);
        $order = $this->order($friend, 'AZK-REF-2', 'TRIPAY-REF-2', 10000);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);

        $this->assertSame('0.000000', $referrer->fresh()->balance);
        $this->assertSame(0, Transaction::where('type', 'referral_reward')->count());
        $this->assertNull($friend->fresh()->referral_rewarded_at);
    }

    public function test_no_reward_without_referrer(): void
    {
        $user = User::factory()->create(['balance' => 0, 'referral_code' => 'SOLO001']);
        $order = $this->order($user, 'AZK-REF-3', 'TRIPAY-REF-3', 100000);

        Http::fake(['tripay.co.id/*' => Http::response(['success' => true, 'data' => $this->paidData($order)])]);

        app(TripayService::class)->reconcile($order);

        $this->assertSame(0, Transaction::where('type', 'referral_reward')->count());
        $this->assertSame(1, Transaction::where('type', 'topup')->count());
    }

    public function test_referral_page_shows_link_and_stats(): void
    {
        $user = User::factory()->create(['referral_code' => 'MYCODE01']);

        $this->actingAs($user)
            ->get(route('referral'))
            ->assertOk()
            ->assertSee('MYCODE01')
            ->assertSee('Referral');
    }

    private function mockGoogleUser(string $email, string $id): void
    {
        $abstractUser = (new SocialiteUser)->map([
            'id' => $id,
            'name' => 'Budi Santoso',
            'email' => $email,
            'avatar' => null,
        ]);
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
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

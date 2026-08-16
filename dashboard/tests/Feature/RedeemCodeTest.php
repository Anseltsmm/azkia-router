<?php

namespace Tests\Feature;

use App\Models\FinancialAuditEvent;
use App\Models\InboxMessage;
use App\Models\RedeemCode;
use App\Models\RedeemCodeBatch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RedeemCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RedeemCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_stores_hash_only_and_requires_correct_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'secret']);
        $data = $this->generationData(['current_password' => 'wrong']);
        $this->actingAs($admin)->post('https://admin.azkia.cloud/redeem-codes', $data)->assertSessionHasErrors('current_password');
        $this->assertDatabaseCount('redeem_codes', 0);

        $response = $this->actingAs($admin)->post('https://admin.azkia.cloud/redeem-codes', $this->generationData());
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        preg_match('/AZK-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}/', $response->getContent(), $match);
        $this->assertNotEmpty($match);
        $code = RedeemCode::firstOrFail();
        $this->assertSame(hash_hmac('sha256', str_replace('-', '', $match[0]), config('app.key')), $code->code_hash);
        $this->assertDatabaseMissing('redeem_codes', ['code_hash' => $match[0]]);
        $this->assertStringNotContainsString($match[0], json_encode($code->getAttributes()));
    }

    public function test_redeem_credits_exact_ledger_audit_and_inbox_once(): void
    {
        [$code, $plaintext] = $this->code();
        $user = User::factory()->create(['balance' => '1.123456']);
        $id = (string) Str::uuid();
        $service = app(RedeemCodeService::class);
        $first = $service->redeem($user, strtolower(str_replace('-', ' ', $plaintext)), $id, '10.0.0.1');
        $second = $service->redeem($user, $plaintext, $id, '10.0.0.1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('3.123456', $user->fresh()->balance);
        $this->assertDatabaseCount('redeem_code_redemptions', 1);
        $this->assertDatabaseHas('transactions', ['id' => $first->transaction_id, 'type' => 'redeem_code', 'reference' => $id]);
        $this->assertDatabaseHas('financial_audit_events', ['transaction_id' => $first->transaction_id, 'action' => 'redeem_code_credit']);
        $this->assertDatabaseCount('inbox_messages', 1);
        $this->assertStringNotContainsString($plaintext, json_encode(Transaction::first()->getAttributes()));
        $this->assertStringNotContainsString($plaintext, json_encode(FinancialAuditEvent::first()->getAttributes()));
        $this->assertStringNotContainsString($plaintext, json_encode(InboxMessage::first()->getAttributes()));
        $this->assertSame(1, $code->fresh()->uses_count);
    }

    public function test_invalid_expired_and_disabled_codes_are_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(RedeemCodeService::class);
        foreach (['invalid', 'expired', 'disabled'] as $case) {
            if ($case === 'invalid') {
                $plaintext = 'AZK-2222-2222-2222';
            } else {
                [$code, $plaintext] = $this->code($case === 'expired' ? ['expires_at' => now()->subMinute()] : []);
                if ($case === 'disabled') {
                    $code->update(['is_active' => false]);
                }
            }
            try {
                $service->redeem($user, $plaintext, (string) Str::uuid(), '10.0.0.1');
                $this->fail('Redeem should fail.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('0.000000', $user->fresh()->balance);
    }

    public function test_account_ip_and_total_limits_are_enforced(): void
    {
        $service = app(RedeemCodeService::class);
        foreach (['account', 'ip', 'total'] as $limit) {
            [$code, $plaintext] = $this->code(['max_total_uses' => $limit === 'total' ? 1 : 5, 'max_uses_per_account' => 1, 'max_uses_per_ip' => 1]);
            $first = User::factory()->create();
            $second = User::factory()->create();
            $service->redeem($first, $plaintext, (string) Str::uuid(), '10.0.0.1');
            $target = $limit === 'account' ? $first : $second;
            $ip = $limit === 'ip' ? '10.0.0.1' : '10.0.0.2';
            $this->expectException(ValidationException::class);
            $service->redeem($target, $plaintext, (string) Str::uuid(), $ip);
        }
    }

    public function test_topup_eligibility_is_enforced(): void
    {
        [$code, $plaintext] = $this->code(['eligible_users' => 'topup']);
        $user = User::factory()->create();
        $service = app(RedeemCodeService::class);

        try {
            $service->redeem($user, $plaintext, (string) Str::uuid(), '10.0.0.1');
            $this->fail('Redeem should require a successful top up.');
        } catch (ValidationException) {
            $this->assertSame('0.000000', $user->fresh()->balance);
        }

        Transaction::create([
            'user_id' => $user->id, 'type' => 'topup', 'amount' => '1.000000',
            'balance_before' => '0.000000', 'balance_after' => '1.000000',
            'currency' => 'USD', 'status' => 'completed',
        ]);

        $service->redeem($user, $plaintext, (string) Str::uuid(), '10.0.0.1');
        $this->assertSame('2.000000', $user->fresh()->balance);
        $this->assertSame(1, $code->fresh()->uses_count);
    }

    private function code(array $batchOverrides = []): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $batch = RedeemCodeBatch::create(array_merge([
            'created_by' => $admin->id, 'generation_idempotency' => (string) Str::uuid(), 'amount' => '2.000000',
            'quantity' => 1, 'max_total_uses' => 10, 'max_uses_per_account' => 10, 'max_uses_per_ip' => 10, 'eligible_users' => 'all', 'is_active' => true,
        ], $batchOverrides));
        $plaintext = 'AZK-2345-6789-ABCD';
        $code = RedeemCode::create(['batch_id' => $batch->id, 'code_hash' => app(RedeemCodeService::class)->codeHash($plaintext), 'code_hint' => 'ABCD']);

        return [$code, $plaintext];
    }

    private function generationData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Promo', 'quantity' => 1, 'amount' => '2.000000', 'max_total_uses' => 1,
            'max_uses_per_account' => 1, 'max_uses_per_ip' => 1, 'eligible_users' => 'all', 'current_password' => 'secret',
            'generation_idempotency' => (string) Str::uuid(),
        ], $overrides);
    }
}

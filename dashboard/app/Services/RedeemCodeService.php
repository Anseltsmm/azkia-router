<?php

namespace App\Services;

use App\Models\FinancialAuditEvent;
use App\Models\InboxMessage;
use App\Models\RedeemCode;
use App\Models\RedeemCodeBatch;
use App\Models\RedeemCodeRedemption;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemCodeService
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generate(User $admin, array $data): array
    {
        return DB::transaction(function () use ($admin, $data) {
            $existing = RedeemCodeBatch::where('generation_idempotency', $data['generation_idempotency'])->lockForUpdate()->first();
            if ($existing) {
                return ['batch' => $existing, 'codes' => null];
            }

            $batch = RedeemCodeBatch::create([
                'created_by' => $admin->id,
                'generation_idempotency' => $data['generation_idempotency'],
                'label' => $data['label'] ?? null,
                'amount' => $data['amount'],
                'quantity' => $data['quantity'],
                'expires_at' => $data['expires_at'] ?? null,
                'max_total_uses' => $data['max_total_uses'],
                'max_uses_per_account' => $data['max_uses_per_account'],
                'max_uses_per_ip' => $data['max_uses_per_ip'],
                'eligible_users' => $data['eligible_users'],
            ]);
            $codes = [];
            while (count($codes) < $data['quantity']) {
                $code = $this->randomCode();
                $hash = $this->codeHash($code);
                if (RedeemCode::where('code_hash', $hash)->exists()) {
                    continue;
                }
                RedeemCode::create(['batch_id' => $batch->id, 'code_hash' => $hash, 'code_hint' => substr($code, -4)]);
                $codes[] = $code;
            }

            return ['batch' => $batch, 'codes' => $codes];
        });
    }

    public function redeem(User $user, string $plaintext, string $requestIdempotency, string $ip): RedeemCodeRedemption
    {
        $prior = RedeemCodeRedemption::where('user_id', $user->id)->where('request_idempotency', $requestIdempotency)->first();
        if ($prior) {
            return $prior;
        }

        $hash = $this->codeHash($plaintext);
        $ipHash = hash_hmac('sha256', $ip, (string) config('app.key'));

        return DB::transaction(function () use ($user, $requestIdempotency, $hash, $ipHash) {
            $prior = RedeemCodeRedemption::where('user_id', $user->id)->where('request_idempotency', $requestIdempotency)->lockForUpdate()->first();
            if ($prior) {
                return $prior;
            }
            $code = RedeemCode::with('batch')->where('code_hash', $hash)->lockForUpdate()->first();
            if (! $code) {
                throw ValidationException::withMessages(['code' => 'Kode redeem tidak valid.']);
            }
            $batch = $code->batch;
            if (! $code->is_active || ! $batch->is_active || ($batch->expires_at && $batch->expires_at->isPast())) {
                throw ValidationException::withMessages(['code' => 'Kode redeem tidak aktif atau telah kedaluwarsa.']);
            }
            if ($code->uses_count >= $batch->max_total_uses) {
                throw ValidationException::withMessages(['code' => 'Batas penggunaan kode telah tercapai.']);
            }
            if ($batch->eligible_users === 'topup' && ! Transaction::where('user_id', $user->id)->whereIn('type', ['topup', 'manual_topup'])->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode ini hanya dapat digunakan oleh akun yang sudah pernah top up.']);
            }
            $redemptions = RedeemCodeRedemption::where('redeem_code_id', $code->id);
            if ((clone $redemptions)->where('user_id', $user->id)->count() >= $batch->max_uses_per_account) {
                throw ValidationException::withMessages(['code' => 'Batas penggunaan kode untuk akun ini telah tercapai.']);
            }
            if ((clone $redemptions)->where('ip_hash', $ipHash)->count() >= $batch->max_uses_per_ip) {
                throw ValidationException::withMessages(['code' => 'Batas penggunaan kode dari jaringan ini telah tercapai.']);
            }

            $lockedUser = User::lockForUpdate()->findOrFail($user->id);
            $before = (string) $lockedUser->balance;
            $after = bcadd($before, (string) $batch->amount, 6);
            $lockedUser->update(['balance' => $after]);
            $transaction = Transaction::create([
                'user_id' => $lockedUser->id, 'type' => 'redeem_code', 'amount' => $batch->amount,
                'balance_before' => $before, 'balance_after' => $after, 'currency' => 'USD',
                'status' => 'completed', 'reference' => $requestIdempotency, 'notes' => 'Redeem code credit',
            ]);
            $redemption = RedeemCodeRedemption::create([
                'redeem_code_id' => $code->id, 'user_id' => $lockedUser->id, 'transaction_id' => $transaction->id,
                'request_idempotency' => $requestIdempotency, 'ip_hash' => $ipHash, 'amount' => $batch->amount,
            ]);
            $code->increment('uses_count');
            FinancialAuditEvent::create([
                'target_user_id' => $lockedUser->id, 'transaction_id' => $transaction->id,
                'action' => 'redeem_code_credit', 'idempotency_key' => $requestIdempotency,
                'amount' => $batch->amount, 'balance_before' => $before, 'balance_after' => $after,
                'metadata' => ['batch_id' => $batch->id, 'code_id' => $code->id],
            ]);
            InboxMessage::firstOrCreate(['dedupe_key' => "redeem-code:{$redemption->id}"], [
                'user_id' => $lockedUser->id, 'subject' => 'Redeem code berhasil',
                'body' => 'Saldo USD '.number_format((float) $batch->amount, 6, '.', '').' telah ditambahkan ke akun Anda.',
            ]);

            return $redemption;
        }, 3);
    }

    public function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function codeHash(string $code): string
    {
        return hash_hmac('sha256', $this->normalize($code), (string) config('app.key'));
    }

    private function randomCode(): string
    {
        $value = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 12; $i++) {
            $value .= self::ALPHABET[random_int(0, $max)];
        }

        return 'AZK-'.substr($value, 0, 4).'-'.substr($value, 4, 4).'-'.substr($value, 8, 4);
    }
}

<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\PaymentOrder;
use App\Models\PaymentSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TripayService
{
    private ?array $settings = null;

    public function channels(): array
    {
        $response = $this->client()->get($this->url('/merchant/payment-channel'));
        $this->ensureSuccess($response->json());

        return collect($response->json('data', []))->where('active', true)->values()->all();
    }

    public function transactionDetail(string $reference): array
    {
        $response = $this->client()->get($this->url('/transaction/detail'), ['reference' => $reference]);
        $body = $response->json();
        $this->ensureSuccess($body);

        return $body['data'];
    }

    public function createTransaction(PaymentOrder $order, string $customerPhone): array
    {
        $payload = [
            'method' => $order->payment_method,
            'merchant_ref' => $order->merchant_ref,
            'amount' => $order->amount_idr,
            'customer_name' => $order->user->name,
            'customer_email' => $order->user->email,
            'customer_phone' => $customerPhone,
            'order_items' => [[
                'sku' => 'AZKIA-CREDIT',
                'name' => 'Saldo Azkia Router',
                'price' => $order->amount_idr,
                'quantity' => 1,
            ]],
            'callback_url' => route('tripay.callback'),
            'return_url' => route('payments.show', $order),
            'expired_time' => now()->addHours((int) $this->setting('expiry_hours'))->timestamp,
            'signature' => $this->transactionSignature($order->merchant_ref, $order->amount_idr),
        ];

        $response = $this->client()->asForm()->post($this->url('/transaction/create'), $payload);
        $body = $response->json();
        $this->ensureSuccess($body);

        return $body['data'];
    }

    public function verifyCallback(Request $request): array
    {
        abort_unless($request->header('X-Callback-Event') === 'payment_status', 400, 'Invalid callback event');
        $expected = hash_hmac('sha256', $request->getContent(), (string) $this->setting('private_key'));
        abort_unless(hash_equals($expected, (string) $request->header('X-Callback-Signature')), 403, 'Invalid callback signature');

        $data = $request->json()->all();
        abort_unless((int) ($data['is_closed_payment'] ?? 0) === 1, 400, 'Invalid payment type');

        return $data;
    }

    public function processCallback(array $data): PaymentOrder
    {
        $order = PaymentOrder::where('merchant_ref', $data['merchant_ref'] ?? '')->firstOrFail();

        return $this->applyTransactionStatus($order, $data);
    }

    public function reconcile(PaymentOrder $order): PaymentOrder
    {
        abort_unless(filled($order->tripay_reference), 422, 'Tripay reference is missing');

        return $this->applyTransactionStatus($order, $this->transactionDetail($order->tripay_reference));
    }

    public function applyTransactionStatus(PaymentOrder $order, array $data): PaymentOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $order = PaymentOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $user = $order->user()->lockForUpdate()->firstOrFail();
            $data = $this->normalizeTransactionDetail($order, $data);
            $status = $data['status'];

            if ($order->status === 'PAID' && $status !== 'PAID') {
                $status = 'PAID';
            }

            $order->status = $status;
            $order->tripay_payload = $data;
            if (! empty($data['paid_at'])) {
                $order->paid_at = date('Y-m-d H:i:s', (int) $data['paid_at']);
            }

            if ($status === 'PAID' && ! $order->credited_at) {
                $user->balance = bcadd((string) $user->balance, (string) $order->credit_usd, 6);
                $user->save();

                Transaction::firstOrCreate(
                    ['payment_order_id' => $order->id],
                    [
                        'user_id' => $user->id,
                        'type' => 'topup',
                        'amount' => $order->credit_usd,
                        'balance_before' => bcsub((string) $user->balance, (string) $order->credit_usd, 6),
                        'balance_after' => $user->balance,
                        'currency' => 'USD',
                        'status' => 'completed',
                        'reference' => $order->merchant_ref,
                        'notes' => 'Topup Tripay Rp '.number_format($order->amount_idr, 0, ',', '.'),
                    ]
                );

                InboxMessage::firstOrCreate(
                    ['dedupe_key' => "deposit:tripay:{$order->id}:credited"],
                    [
                        'user_id' => $user->id,
                        'sender_id' => null,
                        'subject' => 'Deposit berhasil dikreditkan',
                        'body' => 'Deposit sebesar IDR '.number_format($order->amount_idr, 0, ',', '.').' telah berhasil dikreditkan menjadi saldo USD '.number_format((float) $order->credit_usd, 6, '.', '').'. Referensi merchant: '.$order->merchant_ref.'.',
                    ]
                );

                $this->applyReferralReward($order, $user);

                $order->credited_at = now();
            }

            $order->save();

            return $order;
        });
    }

    /**
     * Reward referral: saat user yang direferensikan melakukan top-up pertama
     * (nominal >= minimum), referrer mendapat reward saldo flat. Hanya sekali
     * per user (ditandai referral_rewarded_at).
     */
    private function applyReferralReward(PaymentOrder $order, User $user): void
    {
        if (! $user->referred_by || $user->referral_rewarded_at) {
            return;
        }

        if ((int) $order->amount_idr < (int) config('referral.min_topup_idr')) {
            return;
        }

        $referrer = User::whereKey($user->referred_by)->lockForUpdate()->first();
        if (! $referrer) {
            return;
        }

        $reward = (string) config('referral.reward_usd');
        $balanceBefore = (string) $referrer->balance;
        $referrer->balance = bcadd($balanceBefore, $reward, 6);
        $referrer->save();

        Transaction::create([
            'user_id' => $referrer->id,
            'type' => 'referral_reward',
            'amount' => $reward,
            'balance_before' => $balanceBefore,
            'balance_after' => $referrer->balance,
            'currency' => 'USD',
            'status' => 'completed',
            'reference' => 'REF:'.$user->referral_code,
            'notes' => 'Reward referral dari '.$user->name,
        ]);

        InboxMessage::firstOrCreate(
            ['dedupe_key' => "referral:rewarded:{$user->id}"],
            [
                'user_id' => $referrer->id,
                'sender_id' => null,
                'subject' => 'Reward referral diterima',
                'body' => 'Kamu mendapat reward referral $'.$reward.' dari '.$user->name.' atas top-up pertamanya. Referensi: '.$user->referral_code.'.',
            ]
        );

        $user->referral_rewarded_at = now();
        $user->save();
    }

    private function normalizeTransactionDetail(PaymentOrder $order, array $data): array
    {
        $reference = (string) ($data['reference'] ?? '');
        $merchantRef = (string) ($data['merchant_ref'] ?? '');
        $totalAmount = (int) ($data['total_amount'] ?? $data['amount'] ?? 0);
        $status = strtoupper((string) ($data['status'] ?? ''));

        abort_unless($reference !== '' && hash_equals((string) $order->tripay_reference, $reference), 422, 'Reference mismatch');
        abort_unless($merchantRef !== '' && hash_equals($order->merchant_ref, $merchantRef), 422, 'Merchant reference mismatch');
        abort_unless($totalAmount === (int) $order->total_amount, 422, 'Amount mismatch');
        abort_unless(in_array($status, ['UNPAID', 'PAID', 'FAILED', 'EXPIRED', 'REFUND'], true), 422, 'Invalid transaction status');

        $data['reference'] = $order->tripay_reference;
        $data['merchant_ref'] = $order->merchant_ref;
        $data['total_amount'] = (int) $order->total_amount;
        $data['status'] = $status;

        return $data;
    }

    public function minimumTopup(): int
    {
        return (int) $this->setting('minimum_topup');
    }

    private function transactionSignature(string $merchantRef, int $amount): string
    {
        return hash_hmac('sha256', $this->setting('merchant_code').$merchantRef.$amount, (string) $this->setting('private_key'));
    }

    private function client()
    {
        abort_unless($this->setting('is_active'), 503, 'Pembayaran Tripay belum diaktifkan.');

        return Http::timeout(20)->acceptJson()->withToken((string) $this->setting('api_key'));
    }

    private function url(string $path): string
    {
        $baseUrl = $this->setting('mode') === 'production'
            ? 'https://tripay.co.id/api'
            : 'https://tripay.co.id/api-sandbox';

        return $baseUrl.$path;
    }

    private function setting(string $key): mixed
    {
        if ($this->settings === null) {
            $record = PaymentSetting::where('provider', 'tripay')->first();
            $this->settings = [
                'mode' => $record?->mode ?? config('tripay.mode'),
                'api_key' => $record?->secret('api_key_encrypted') ?? config('tripay.api_key'),
                'private_key' => $record?->secret('private_key_encrypted') ?? config('tripay.private_key'),
                'merchant_code' => $record?->secret('merchant_code_encrypted') ?? config('tripay.merchant_code'),
                'minimum_topup' => $record?->minimum_topup ?? config('tripay.minimum_topup'),
                'expiry_hours' => $record?->expiry_hours ?? config('tripay.expiry_hours'),
                'is_active' => $record ? $record->is_active : filled(config('tripay.api_key')),
            ];
        }

        return $this->settings[$key] ?? null;
    }

    private function ensureSuccess(?array $body): void
    {
        if (! ($body['success'] ?? false)) {
            throw new RuntimeException((string) ($body['message'] ?? 'Tripay tidak dapat memproses permintaan.'));
        }
    }
}

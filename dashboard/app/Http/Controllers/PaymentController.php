<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrder;
use App\Services\ExchangeRateService;
use App\Services\TripayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function create(TripayService $tripay)
    {
        $channels = [];
        $error = null;

        try {
            $channels = $tripay->channels();
        } catch (\Throwable $e) {
            $error = __('dashboard.flash.channels_failed', ['message' => $e->getMessage()]);
        }

        return view('user.topup', [
            'channels' => $channels,
            'error' => $error,
            'minimumTopup' => $tripay->minimumTopup(),
        ]);
    }

    public function store(Request $request, TripayService $tripay, ExchangeRateService $exchange)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$tripay->minimumTopup(), 'max:10000000'],
            'method' => ['required', 'string', 'max:50'],
            'customer_phone' => ['required', 'string', 'regex:/^(?:\+62|62|0)8[1-9][0-9]{6,11}$/'],
        ]);

        $channels = collect($tripay->channels());
        $channel = $channels->firstWhere('code', $data['method']);
        abort_unless($channel && ($channel['active'] ?? false), 422, __('dashboard.flash.payment_unavailable'));
        abort_unless($data['amount'] >= (int) ($channel['minimum_amount'] ?? 0), 422, __('dashboard.flash.below_minimum'));
        abort_unless($data['amount'] <= (int) ($channel['maximum_amount'] ?? PHP_INT_MAX), 422, __('dashboard.flash.above_maximum'));

        $rate = $exchange->usdToIdr();
        $creditUsd = bcdiv((string) $data['amount'], $rate, 6);
        $order = PaymentOrder::create([
            'user_id' => Auth::id(),
            'merchant_ref' => 'AZK-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6)),
            'payment_method' => $data['method'],
            'payment_name' => $channel['name'] ?? $data['method'],
            'amount_idr' => $data['amount'],
            'credit_usd' => $creditUsd,
            'exchange_rate' => $rate,
        ]);

        try {
            $result = $tripay->createTransaction($order->load('user'), $this->normalizePhone($data['customer_phone']));
            $order->update([
                'tripay_reference' => $result['reference'],
                'payment_name' => $result['payment_name'] ?? $order->payment_name,
                'fee_customer' => $result['fee_customer'] ?? 0,
                'total_amount' => $result['total_amount'] ?? $result['amount'] ?? $data['amount'],
                'status' => $result['status'] ?? 'UNPAID',
                'pay_code' => $result['pay_code'] ?? null,
                'pay_url' => $result['pay_url'] ?? null,
                'checkout_url' => $result['checkout_url'] ?? null,
                'qr_url' => $result['qr_url'] ?? null,
                'expires_at' => isset($result['expired_time']) ? date('Y-m-d H:i:s', (int) $result['expired_time']) : null,
                'tripay_payload' => $result,
            ]);
        } catch (\Throwable $e) {
            $order->delete();

            return back()->withInput()->withErrors(['payment' => __('dashboard.flash.payment_failed', ['message' => $e->getMessage()])]);
        }

        return redirect()->route('payments.show', $order);
    }

    public function show(PaymentOrder $paymentOrder, TripayService $tripay)
    {
        abort_unless($paymentOrder->user_id === Auth::id(), 403);

        if ($paymentOrder->tripay_reference && $paymentOrder->status === 'UNPAID') {
            try {
                $tripay->reconcile($paymentOrder);
            } catch (\Throwable) {
            }
        }

        return view('user.payment', ['payment' => $paymentOrder->refresh()]);
    }

    public function callback(Request $request, TripayService $tripay)
    {
        $tripay->processCallback($tripay->verifyCallback($request));

        return response()->json(['success' => true]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        return str_starts_with($phone, '0') ? '62'.substr($phone, 1) : $phone;
    }
}

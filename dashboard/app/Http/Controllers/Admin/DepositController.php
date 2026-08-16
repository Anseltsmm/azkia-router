<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialAuditEvent;
use App\Models\InboxMessage;
use App\Models\PaymentOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TripayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DepositController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);
        $sort = in_array($request->input('sort'), ['created_at', 'credit_usd', 'amount_idr', 'status', 'credited_at'], true) ? $request->input('sort') : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        return view('admin.deposits.index', [
            'orders' => $query->with(['user', 'transaction'])->orderBy($sort, $direction)->paginate(25)->withQueryString(),
            'stats' => [
                'total' => PaymentOrder::count(),
                'pending' => PaymentOrder::whereIn('status', ['UNPAID', 'PENDING'])->count(),
                'paid' => PaymentOrder::where('status', 'PAID')->count(),
                'credited' => PaymentOrder::whereNotNull('credited_at')->count(),
                'credit' => PaymentOrder::whereNotNull('credited_at')->sum('credit_usd'),
            ],
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function show(PaymentOrder $paymentOrder)
    {
        $paymentOrder->load(['user', 'transaction']);
        $audit = FinancialAuditEvent::with('actor')->where('payment_order_id', $paymentOrder->id)->latest()->get();

        return view('admin.deposits.show', [
            'order' => $paymentOrder,
            'payload' => $this->sanitizePayload($paymentOrder->tripay_payload ?? []),
            'audit' => $audit,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $count = min(50000, $this->filteredQuery($request)->count());
        $this->audit($request, 'deposit_export', null, ['rows' => $count, 'filters' => $request->only(['search', 'status', 'method', 'date_from', 'date_to', 'credited'])]);

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Merchant Ref', 'Tripay Ref', 'User', 'Email', 'Method', 'Status', 'IDR', 'USD', 'Credited At', 'Created At']);
            $this->filteredQuery($request)->with('user')->latest()->limit(50000)->chunk(500, function ($orders) use ($out) {
                foreach ($orders as $order) {
                    fputcsv($out, array_map($this->csvCell(...), [$order->merchant_ref, $order->tripay_reference, $order->user->name, $order->user->email, $order->payment_method, $order->status, $order->amount_idr, $order->credit_usd, $order->credited_at, $order->created_at]));
                }
            });
            fclose($out);
        }, 'deposits-'.now()->format('Ymd-His').'.csv', ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reconcile(Request $request, PaymentOrder $paymentOrder, TripayService $tripay)
    {
        try {
            $result = $tripay->reconcile($paymentOrder);
            $this->audit($request, 'deposit_reconcile_success', $result, ['status' => $result->status]);

            return back()->with('success', 'Deposit berhasil direkonsiliasi.');
        } catch (Throwable $e) {
            $this->audit($request, 'deposit_reconcile_failure', $paymentOrder, ['error' => class_basename($e)]);

            return back()->with('error', 'Rekonsiliasi gagal.');
        }
    }

    public function reconcileBatch(Request $request, TripayService $tripay)
    {
        $data = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $orders = $this->filteredQuery($request)->whereIn('status', ['UNPAID', 'PENDING'])->oldest()->limit($data['limit'] ?? 100)->get();
        $ok = 0;
        $failed = 0;
        foreach ($orders as $order) {
            try {
                $result = $tripay->reconcile($order);
                $this->audit($request, 'deposit_reconcile_success', $result, ['batch' => true, 'status' => $result->status]);
                $ok++;
            } catch (Throwable $e) {
                $this->audit($request, 'deposit_reconcile_failure', $order, ['batch' => true, 'error' => class_basename($e)]);
                $failed++;
            }
        }
        $this->audit($request, 'deposit_reconcile_batch', null, ['processed' => $orders->count(), 'succeeded' => $ok, 'failed' => $failed]);

        return back()->with('success', "Batch selesai: {$ok} berhasil, {$failed} gagal.");
    }

    public function manualCredit(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'user_id' => ['required', Rule::exists('users', 'id')],
            'amount' => ['required', 'regex:/^\d{1,8}(\.\d{1,6})?$/', 'decimal:0,6', 'gt:0', 'lte:99999999.999999'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini tidak valid.']);
        }
        if (FinancialAuditEvent::where('idempotency_key', $data['idempotency_key'])->exists()) {
            return back()->with('success', 'Kredit manual ini sudah diproses.');
        }

        DB::transaction(function () use ($request, $data) {
            $user = User::lockForUpdate()->findOrFail($data['user_id']);
            $before = (string) $user->balance;
            $after = bcadd($before, $data['amount'], 6);
            $user->balance = $after;
            $user->save();
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'manual_topup',
                'amount' => $data['amount'],
                'balance_before' => $before,
                'balance_after' => $after,
                'currency' => 'USD',
                'status' => 'completed',
                'reference' => $data['idempotency_key'],
                'notes' => $data['reason'],
            ]);
            FinancialAuditEvent::create([
                'actor_id' => $request->user()->id,
                'target_user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'action' => 'manual_credit',
                'idempotency_key' => $data['idempotency_key'],
                'amount' => $data['amount'],
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => $data['reason'],
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
            InboxMessage::firstOrCreate(
                ['dedupe_key' => "deposit:manual:{$data['idempotency_key']}:credited"],
                [
                    'user_id' => $user->id,
                    'sender_id' => null,
                    'subject' => 'Saldo berhasil dikreditkan',
                    'body' => 'Saldo USD '.number_format((float) $data['amount'], 6, '.', '').' telah berhasil ditambahkan sebagai penyesuaian saldo.',
                ]
            );
        });

        return back()->with('success', 'Saldo berhasil dikreditkan.');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = PaymentOrder::query();
        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('merchant_ref', 'like', "%{$search}%")->orWhere('tripay_reference', 'like', "%{$search}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        foreach (['status' => 'status', 'method' => 'payment_method'] as $input => $column) {
            if (filled($request->input($input))) {
                $query->where($column, $request->input($input));
            }
        }
        if ($request->input('credited') === 'yes') {
            $query->whereNotNull('credited_at');
        } elseif ($request->input('credited') === 'no') {
            $query->whereNull('credited_at');
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    private function audit(Request $request, string $action, ?PaymentOrder $order, array $metadata): void
    {
        FinancialAuditEvent::create(['actor_id' => $request->user()->id, 'target_user_id' => $order?->user_id, 'payment_order_id' => $order?->id, 'action' => $action, 'metadata' => $metadata, 'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000)]);
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitive = ['api_key', 'private_key', 'signature', 'token', 'authorization', 'customer_phone', 'customer_email'];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $payload[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            }
        }

        return $payload;
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
    }
}

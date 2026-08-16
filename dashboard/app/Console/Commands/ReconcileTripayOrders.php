<?php

namespace App\Console\Commands;

use App\Models\PaymentOrder;
use App\Services\TripayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileTripayOrders extends Command
{
    protected $signature = 'tripay:reconcile {--limit=100 : Maximum orders to process} {--older-than=5 : Minimum order age in minutes}';

    protected $description = 'Reconcile pending Tripay orders with Tripay transaction status';

    public function handle(TripayService $tripay): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $olderThan = max(0, (int) $this->option('older-than'));
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        PaymentOrder::query()
            ->where('status', 'UNPAID')
            ->whereNotNull('tripay_reference')
            ->where('created_at', '<=', now()->subMinutes($olderThan))
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (PaymentOrder $order) use ($tripay, &$processed, &$succeeded, &$failed): void {
                $processed++;

                try {
                    $tripay->reconcile($order);
                    $succeeded++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Tripay order reconciliation failed', [
                        'payment_order_id' => $order->id,
                        'merchant_ref' => $order->merchant_ref,
                        'error' => $exception->getMessage(),
                    ]);
                    $this->error("Order {$order->id}: {$exception->getMessage()}");
                }
            });

        $summary = "Tripay reconciliation complete: processed={$processed}, succeeded={$succeeded}, failed={$failed}";
        Log::info($summary);
        $this->info($summary);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

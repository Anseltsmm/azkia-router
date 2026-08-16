<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'target_user_id', 'payment_order_id', 'transaction_id', 'action', 'idempotency_key', 'amount', 'balance_before', 'balance_after', 'reason', 'metadata', 'ip', 'user_agent'])]
class FinancialAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'balance_before' => 'decimal:6',
            'balance_after' => 'decimal:6',
            'metadata' => 'array',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function paymentOrder()
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}

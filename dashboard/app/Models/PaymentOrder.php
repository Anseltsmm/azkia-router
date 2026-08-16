<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'merchant_ref', 'tripay_reference', 'payment_method', 'payment_name', 'amount_idr', 'credit_usd', 'exchange_rate', 'fee_customer', 'total_amount', 'status', 'pay_code', 'pay_url', 'checkout_url', 'qr_url', 'expires_at', 'paid_at', 'credited_at', 'tripay_payload'])]
class PaymentOrder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'credit_usd' => 'decimal:6',
            'exchange_rate' => 'decimal:4',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'credited_at' => 'datetime',
            'tripay_payload' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}

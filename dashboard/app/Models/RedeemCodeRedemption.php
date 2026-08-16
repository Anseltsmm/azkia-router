<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['redeem_code_id', 'user_id', 'transaction_id', 'request_idempotency', 'ip_hash', 'amount'])]
class RedeemCodeRedemption extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount' => 'decimal:6'];
    }

    public function code()
    {
        return $this->belongsTo(RedeemCode::class, 'redeem_code_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}

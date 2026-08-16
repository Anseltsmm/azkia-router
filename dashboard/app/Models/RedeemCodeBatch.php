<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by', 'generation_idempotency', 'label', 'amount', 'quantity', 'max_total_uses', 'max_uses_per_account', 'max_uses_per_ip', 'eligible_users', 'expires_at', 'is_active'])]
class RedeemCodeBatch extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:6', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function codes()
    {
        return $this->hasMany(RedeemCode::class, 'batch_id');
    }
}

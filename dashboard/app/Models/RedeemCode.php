<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['batch_id', 'code_hash', 'code_hint', 'uses_count', 'is_active'])]
class RedeemCode extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function batch()
    {
        return $this->belongsTo(RedeemCodeBatch::class, 'batch_id');
    }

    public function redemptions()
    {
        return $this->hasMany(RedeemCodeRedemption::class);
    }
}

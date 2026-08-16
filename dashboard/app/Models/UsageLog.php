<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'api_key_id', 'model', 'endpoint', 'input_tokens', 'output_tokens', 'cost', 'latency_ms', 'status_code', 'request_id', 'upstream_request_id', 'billing_id', 'usage_source', 'usage_quality', 'ip_address', 'user_agent', 'cache_read', 'cache_write'])]
class UsageLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:6',
            'cache_read' => 'boolean',
            'cache_write' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function billingEvent()
    {
        return $this->belongsTo(BillingEvent::class, 'billing_id');
    }
}

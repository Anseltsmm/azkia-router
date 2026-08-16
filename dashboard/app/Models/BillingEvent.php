<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'user_id', 'api_key_id', 'model', 'endpoint', 'input_tokens', 'output_tokens', 'cost', 'upstream_request_id', 'usage_source', 'latency_ms', 'status_code', 'failure_reason', 'settled_at', 'status', 'payload', 'retry_count', 'next_retry_at', 'last_attempt_at', 'last_error', 'reserved_cost', 'reserved_tokens', 'quota_period_start', 'pricing_snapshot', 'reserved_at', 'upstream_started_at', 'released_at', 'settlement_kind', 'usage_quality', 'stream_failure_reason'])]
class BillingEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['cost' => 'decimal:6', 'reserved_cost' => 'decimal:6', 'payload' => 'array', 'pricing_snapshot' => 'array', 'quota_period_start' => 'date', 'settled_at' => 'datetime', 'next_retry_at' => 'datetime', 'last_attempt_at' => 'datetime', 'reserved_at' => 'datetime', 'upstream_started_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function usageLog()
    {
        return $this->hasOne(UsageLog::class, 'billing_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'billing_id');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ai_model_id', 'input_per_million', 'output_per_million', 'original_input_per_million', 'original_output_per_million', 'cache_read_input_per_million', 'cache_write_per_million', 'currency', 'is_active', 'is_promo', 'promo_starts_at', 'promo_ends_at'])]
class PricingRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_promo' => 'boolean',
            'promo_starts_at' => 'datetime',
            'promo_ends_at' => 'datetime',
        ];
    }

    public function aiModel()
    {
        return $this->belongsTo(AiModel::class);
    }

    public function getPromoIsActiveAttribute(): bool
    {
        return $this->is_promo
            && ($this->promo_starts_at === null || $this->promo_starts_at->lte(now()))
            && ($this->promo_ends_at === null || $this->promo_ends_at->gte(now()));
    }

    public function getEffectiveInputPriceAttribute(): string
    {
        return (string) ($this->promo_is_active ? $this->input_per_million : ($this->original_input_per_million ?? $this->input_per_million));
    }

    public function getEffectiveOutputPriceAttribute(): string
    {
        return (string) ($this->promo_is_active ? $this->output_per_million : ($this->original_output_per_million ?? $this->output_per_million));
    }
}

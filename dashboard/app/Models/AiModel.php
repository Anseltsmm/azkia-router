<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['provider_id', 'public_name', 'upstream_name', 'type', 'capabilities', 'icon_path', 'is_active', 'context_window', 'rate_limit_per_minute'])]
class AiModel extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capabilities' => 'array',
            'rate_limit_per_minute' => 'integer',
        ];
    }

    /**
     * URL publik ikon model (via disk public). Null jika tidak ada ikon.
     */
    public function getIconUrlAttribute(): ?string
    {
        if (! $this->icon_path) {
            return null;
        }

        return Storage::disk('public')->url($this->icon_path);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function latestPricingRule()
    {
        return $this->hasOne(PricingRule::class)->where('is_active', true)->latestOfMany();
    }
}

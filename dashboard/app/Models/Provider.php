<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'base_url', 'api_key_encrypted', 'is_active', 'priority', 'timeout_seconds'])]
class Provider extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function aiModels()
    {
        return $this->hasMany(AiModel::class);
    }
}

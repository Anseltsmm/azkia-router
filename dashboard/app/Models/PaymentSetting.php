<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['provider', 'mode', 'api_key_encrypted', 'private_key_encrypted', 'merchant_code_encrypted', 'minimum_topup', 'expiry_hours', 'is_active'])]
class PaymentSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function secret(string $attribute): ?string
    {
        $value = $this->getAttribute($attribute);

        return $value ? Crypt::decryptString($value) : null;
    }
}

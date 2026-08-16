<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['support_message_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class SupportAttachment extends Model
{
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function message()
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

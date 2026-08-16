<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ticket_number', 'user_id', 'subject', 'category', 'priority', 'status', 'assigned_admin_id', 'request_reference', 'billing_event_id', 'payment_order_id', 'last_message_at', 'last_user_message_at', 'last_admin_message_at', 'last_user_read_at', 'last_admin_read_at', 'resolved_at', 'closed_at'])]
class SupportTicket extends Model
{
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'last_user_message_at' => 'datetime', 'last_admin_message_at' => 'datetime', 'last_user_read_at' => 'datetime', 'last_admin_read_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function billingEvent()
    {
        return $this->belongsTo(BillingEvent::class);
    }

    public function paymentOrder()
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}

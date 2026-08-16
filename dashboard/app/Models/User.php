<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'is_admin', 'balance', 'status', 'payg_enabled', 'referral_code', 'referred_by', 'referral_rewarded_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'balance' => 'decimal:6',
            'reserved_balance' => 'decimal:6',
            'payg_enabled' => 'boolean',
            'referral_rewarded_at' => 'datetime',
        ];
    }

    /**
     * Generate kode referral unik (huruf besar acak 8 karakter).
     */
    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function plans()
    {
        return $this->hasMany(UserPlan::class);
    }

    public function activePlans()
    {
        return $this->plans()->active();
    }

    /**
     * Sisa kuota plan yang masih bisa dipakai (semua plan aktif; plan reset
     * harian dihitung dari sisa kuota hariannya).
     */
    public function getPlanTokensRemainingAttribute(): int
    {
        return (int) $this->activePlans()->get()->sum(fn (UserPlan $up) => $up->remaining_tokens);
    }

    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(UsageLog::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function inboxMessages()
    {
        return $this->hasMany(InboxMessage::class);
    }

    public function sentInboxMessages()
    {
        return $this->hasMany(InboxMessage::class, 'sender_id');
    }

    public function paymentOrders()
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function billingEvents()
    {
        return $this->hasMany(BillingEvent::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function assignedSupportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'assigned_admin_id');
    }

    public function supportMessages()
    {
        return $this->hasMany(SupportMessage::class, 'sender_id');
    }
}

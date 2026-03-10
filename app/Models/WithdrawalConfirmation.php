<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WithdrawalConfirmation extends Model
{
    protected $fillable = [
        'code',
        'withdrawal_id',
        'user_id',
        'chat_id',
        'amount_cents',
        'payment_method',
        'status',
        'confirmed_at',
        'cancelled_at',
        'expires_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWithdrawal(Builder $query, string $withdrawalId): Builder
    {
        return $query->where('withdrawal_id', $withdrawalId);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /* ----------------------------------------------------------------
     * Methods
     * ---------------------------------------------------------------- */

    /**
     * Confirm this withdrawal.
     */
    public function confirm(): bool
    {
        if ($this->status !== 'pending' || $this->isExpired()) {
            return false;
        }

        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return true;
    }

    /**
     * Cancel this withdrawal confirmation.
     */
    public function cancel(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return true;
    }

    /**
     * Check if this confirmation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get the amount formatted in dollars.
     */
    public function getAmountDollarsAttribute(): float
    {
        return $this->amount_cents / 100;
    }
}

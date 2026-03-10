<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnboardingLink extends Model
{
    protected $fillable = [
        'code',
        'user_id',
        'role',
        'status',
        'telegram_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'deep_link',
        'linked_at',
        'expires_at',
    ];

    protected $casts = [
        'telegram_id' => 'integer',
        'linked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeLinked(Builder $query): Builder
    {
        return $query->where('status', 'linked');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /* ----------------------------------------------------------------
     * Methods
     * ---------------------------------------------------------------- */

    /**
     * Mark this link as linked with Telegram user data.
     */
    public function markAsLinked(array $telegramData): void
    {
        $this->update([
            'status' => 'linked',
            'telegram_id' => $telegramData['id'] ?? null,
            'telegram_username' => $telegramData['username'] ?? null,
            'telegram_first_name' => $telegramData['first_name'] ?? null,
            'telegram_last_name' => $telegramData['last_name'] ?? null,
            'linked_at' => now(),
        ]);
    }

    /**
     * Check if this link has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if this link is still valid (pending and not expired).
     */
    public function isValid(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }
}

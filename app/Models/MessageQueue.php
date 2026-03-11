<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MessageQueue extends Model
{
    protected $table = 'message_queue';

    protected $fillable = [
        'chat_id',
        'message',
        'parse_mode',
        'status',
        'attempts',
        'max_retries',
        'idempotency_key',
        'error',
        'source',
        'bot_slug',
        'next_retry_at',
        'claimed_at',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_retries' => 'integer',
        'next_retry_at' => 'datetime',
        'claimed_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeDead(Builder $query): Builder
    {
        return $query->where('status', 'dead');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where(function (Builder $q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /* ----------------------------------------------------------------
     * State transition methods
     * ---------------------------------------------------------------- */

    /**
     * Claim this message for processing (atomic via status check).
     */
    public function markAsClaimed(): bool
    {
        $updated = self::where('id', $this->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'processing',
                'claimed_at' => now(),
            ]);

        if ($updated) {
            $this->status = 'processing';
            $this->claimed_at = now();

            return true;
        }

        return false;
    }

    /**
     * Mark as successfully sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    /**
     * Mark as failed, schedule retry if attempts remain.
     */
    public function markAsFailed(string $error): void
    {
        $this->increment('attempts');

        if ($this->attempts >= $this->max_retries) {
            $this->markAsDead($error);

            return;
        }

        // Exponential backoff: 2^attempts seconds (2s, 4s, 8s...)
        $delay = pow(2, $this->attempts);

        $this->update([
            'status' => 'pending',
            'error' => $error,
            'next_retry_at' => now()->addSeconds($delay),
        ]);
    }

    /**
     * Mark as dead (exhausted all retries).
     */
    public function markAsDead(?string $error = null): void
    {
        $this->update([
            'status' => 'dead',
            'error' => $error ?? $this->error,
        ]);
    }
}

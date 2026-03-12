<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    protected $fillable = [
        'date',
        'messages_sent',
        'messages_failed',
        'peak_per_second',
    ];

    protected $casts = [
        'date' => 'date',
        'messages_sent' => 'integer',
        'messages_failed' => 'integer',
        'peak_per_second' => 'integer',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopeToday(Builder $query): Builder
    {
        return $query->where('date', now()->toDateString());
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('date', $date);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /* ----------------------------------------------------------------
     * Methods
     * ---------------------------------------------------------------- */

    /**
     * Get or create today's rate limit record.
     */
    public static function today(): self
    {
        return self::firstOrCreate(
            ['date' => now()->toDateString()],
            [
                'messages_sent' => 0,
                'messages_failed' => 0,
                'peak_per_second' => 0,
            ]
        );
    }

    /**
     * Record a sent message and update peak if needed.
     */
    public function recordSent(int $currentPerSecond = 0): void
    {
        $this->increment('messages_sent');

        if ($currentPerSecond > $this->peak_per_second) {
            $this->update(['peak_per_second' => $currentPerSecond]);
        }
    }

    /**
     * Record a failed message.
     */
    public function recordFailed(): void
    {
        $this->increment('messages_failed');
    }

    /**
     * Total messages attempted today.
     */
    public function getTotalAttemptsAttribute(): int
    {
        return $this->messages_sent + $this->messages_failed;
    }
}

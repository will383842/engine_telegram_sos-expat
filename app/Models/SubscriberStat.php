<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubscriberStat extends Model
{
    protected $fillable = [
        'date',
        'total_subscribers',
        'active_subscribers',
        'messages_sent',
        'messages_failed',
        'by_role',
    ];

    protected $casts = [
        'date' => 'date',
        'total_subscribers' => 'integer',
        'active_subscribers' => 'integer',
        'messages_sent' => 'integer',
        'messages_failed' => 'integer',
        'by_role' => 'array',
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
     * Get or create today's stat record.
     */
    public static function today(): self
    {
        return self::firstOrCreate(
            ['date' => now()->toDateString()],
            [
                'total_subscribers' => 0,
                'active_subscribers' => 0,
                'messages_sent' => 0,
                'messages_failed' => 0,
                'by_role' => [],
            ]
        );
    }

    /**
     * Increment sent message counter for today.
     */
    public function incrementSent(int $count = 1): void
    {
        $this->increment('messages_sent', $count);
    }

    /**
     * Increment failed message counter for today.
     */
    public function incrementFailed(int $count = 1): void
    {
        $this->increment('messages_failed', $count);
    }

    /**
     * Total messages (sent + failed) for the day.
     */
    public function getTotalMessagesAttribute(): int
    {
        return $this->messages_sent + $this->messages_failed;
    }

    /**
     * Success rate as a percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->total_messages;

        if ($total === 0) {
            return 0;
        }

        return round($this->messages_sent / $total * 100, 1);
    }
}

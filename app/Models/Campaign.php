<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'message',
        'parse_mode',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by',
        'filters',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     * Relationships
     * ---------------------------------------------------------------- */

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeScheduledBefore(Builder $query, $dateTime): Builder
    {
        return $query->where('scheduled_at', '<=', $dateTime);
    }

    /* ----------------------------------------------------------------
     * Computed attributes
     * ---------------------------------------------------------------- */

    /**
     * Percentage of recipients processed.
     */
    public function getProgressPercentAttribute(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return round(($this->sent_count + $this->failed_count) / $this->total_recipients * 100, 1);
    }

    /**
     * Whether all recipients have been processed.
     */
    public function isFinished(): bool
    {
        return ($this->sent_count + $this->failed_count) >= $this->total_recipients;
    }

    /**
     * Mark campaign as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark campaign as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}

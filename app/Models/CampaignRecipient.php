<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'chat_id',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /* ----------------------------------------------------------------
     * Relationships
     * ---------------------------------------------------------------- */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /* ----------------------------------------------------------------
     * State transitions
     * ---------------------------------------------------------------- */

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
        ]);
    }
}

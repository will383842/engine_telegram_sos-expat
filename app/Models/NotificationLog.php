<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'event_type',
        'chat_id',
        'message',
        'status',
        'error',
        'variables',
        'filters',
        'bot_slug',
    ];

    protected $casts = [
        'variables' => 'array',
        'filters' => 'array',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopeByEventType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeFiltered(Builder $query): Builder
    {
        return $query->where('status', 'filtered');
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeForChatId(Builder $query, string $chatId): Builder
    {
        return $query->where('chat_id', $chatId);
    }
}

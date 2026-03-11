<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBot extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'token',
        'recipient_chat_id',
        'notifications',
        'is_active',
    ];

    protected $casts = [
        'notifications' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get a bot by its slug.
     */
    public static function bySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->where('is_active', true)->first();
    }

    /**
     * Get all active bots that have a given event enabled.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function forEvent(string $eventType): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('is_active', true)
            ->get()
            ->filter(function (self $bot) use ($eventType) {
                $notifications = $bot->notifications ?? [];
                return !empty($notifications[$eventType]);
            });
    }

    /**
     * Check if a specific event is enabled for this bot.
     */
    public function isEventEnabled(string $eventType): bool
    {
        return !empty(($this->notifications ?? [])[$eventType]);
    }
}

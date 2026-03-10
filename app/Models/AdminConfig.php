<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminConfig extends Model
{
    protected $fillable = [
        'recipient_phone_number',
        'recipient_chat_id',
        'notifications',
        'updated_by',
    ];

    protected $casts = [
        'notifications' => 'array',
    ];

    /**
     * Singleton pattern - always returns the single config row.
     */
    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }

    /**
     * Check if a specific notification event is enabled.
     */
    public function isEventEnabled(string $eventType): bool
    {
        $notifications = $this->notifications ?? [];

        return !empty($notifications[$eventType]);
    }

    /**
     * Enable or disable a notification event.
     */
    public function setEventEnabled(string $eventType, bool $enabled): self
    {
        $notifications = $this->notifications ?? [];
        $notifications[$eventType] = $enabled;
        $this->notifications = $notifications;

        return $this;
    }
}

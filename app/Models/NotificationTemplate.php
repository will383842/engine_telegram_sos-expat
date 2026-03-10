<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'event_type',
        'language',
        'template',
        'is_custom',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopeByEvent(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_custom', true);
    }

    /* ----------------------------------------------------------------
     * 3-level fallback: event+lang -> lang "general" -> EN "general"
     * ---------------------------------------------------------------- */

    /**
     * Get the best matching template with 3-level fallback:
     *  1. Exact event_type + language
     *  2. "general" event_type + same language
     *  3. "general" event_type + "en"
     */
    public static function getBest(string $eventType, string $language): ?self
    {
        // Level 1: exact match
        $template = self::where('event_type', $eventType)
            ->where('language', $language)
            ->first();

        if ($template) {
            return $template;
        }

        // Level 2: general template in same language
        $template = self::where('event_type', 'general')
            ->where('language', $language)
            ->first();

        if ($template) {
            return $template;
        }

        // Level 3: general template in English
        return self::where('event_type', 'general')
            ->where('language', 'en')
            ->first();
    }

    /**
     * Render the template by replacing {{VARIABLE}} placeholders.
     */
    public function render(array $variables = []): string
    {
        $text = $this->template;

        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . strtoupper($key) . '}}', (string) $value, $text);
        }

        return $text;
    }
}

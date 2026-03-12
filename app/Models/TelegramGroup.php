<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'link',
        'language',
        'role',
        'type',
        'continent_code',
        'enabled',
        'managers',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'managers' => 'array',
    ];

    /* ----------------------------------------------------------------
     * Scopes
     * ---------------------------------------------------------------- */

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeContinent(Builder $query): Builder
    {
        return $query->where('type', 'continent');
    }

    public function scopeLanguageFallback(Builder $query): Builder
    {
        return $query->where('type', 'language');
    }

    /* ----------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */

    public function hasLink(): bool
    {
        return $this->link !== '' && $this->link !== null;
    }
}

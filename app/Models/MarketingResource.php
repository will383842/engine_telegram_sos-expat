<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingResource extends Model
{
    use HasUuids;

    protected $table = 'marketing_resources';

    protected $fillable = [
        'target_roles',
        'category',
        'type',
        'name',
        'description',
        'content',
        'file_path',
        'thumbnail_path',
        'file_format',
        'file_size',
        'placeholders',
        'seo_keywords',
        'word_count',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'name'         => 'array',
            'description'  => 'array',
            'content'      => 'array',
            'placeholders' => 'array',
            'seo_keywords' => 'array',
            'is_active'    => 'boolean',
        ];
    }

    /* ──────────────────────────────────────────
     * Supported languages & roles
     * ────────────────────────────────────────── */

    public const LANGUAGES = ['fr', 'en', 'es', 'pt', 'ar', 'de', 'zh', 'ru', 'hi'];

    public const ROLES = [
        'chatter',
        'captain',
        'influencer',
        'blogger',
        'group_admin',
        'partner',
        'press',
    ];

    public const CATEGORIES = [
        'brand',
        'promotional',
        'social_media',
        'templates',
        'seo',
        'group_posts',
        'group_assets',
        'partner_kit',
        'press_kit',
        'recruitment',
    ];

    public const TYPES = [
        'logo',
        'image',
        'banner',
        'text',
        'template',
        'article',
        'video',
        'document',
        'widget',
    ];

    /* ──────────────────────────────────────────
     * Translation helper — fallback: lang → en → fr
     * ────────────────────────────────────────── */

    public function translate(string $field, string $lang): ?string
    {
        $translations = $this->{$field};

        if (!is_array($translations)) {
            return null;
        }

        return $translations[$lang]
            ?? $translations['en']
            ?? $translations['fr']
            ?? null;
    }

    /**
     * Return the full resource as a translated array for API response.
     */
    public function toTranslated(string $lang): array
    {
        return [
            'id'             => $this->id,
            'target_roles'   => $this->target_roles,
            'category'       => $this->category,
            'type'           => $this->type,
            'name'           => $this->translate('name', $lang),
            'description'    => $this->translate('description', $lang),
            'content'        => $this->translate('content', $lang),
            'file_path'      => $this->file_path,
            'thumbnail_path' => $this->thumbnail_path,
            'file_format'    => $this->file_format,
            'file_size'      => $this->file_size,
            'placeholders'   => $this->placeholders,
            'seo_keywords'   => $this->seo_keywords,
            'word_count'     => $this->word_count,
            'download_count' => $this->download_count,
            'copy_count'     => $this->copy_count,
            'sort_order'     => $this->sort_order,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Return full resource with ALL translations (for admin).
     */
    public function toAdmin(): array
    {
        return [
            'id'             => $this->id,
            'target_roles'   => $this->target_roles,
            'category'       => $this->category,
            'type'           => $this->type,
            'name'           => $this->name,
            'description'    => $this->description,
            'content'        => $this->content,
            'file_path'      => $this->file_path,
            'thumbnail_path' => $this->thumbnail_path,
            'file_format'    => $this->file_format,
            'file_size'      => $this->file_size,
            'placeholders'   => $this->placeholders,
            'seo_keywords'   => $this->seo_keywords,
            'word_count'     => $this->word_count,
            'download_count' => $this->download_count,
            'copy_count'     => $this->copy_count,
            'is_active'      => $this->is_active,
            'sort_order'     => $this->sort_order,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }

    /* ──────────────────────────────────────────
     * Scopes
     * ────────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->whereJsonContains('target_roles', $role);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('category')->orderBy('sort_order');
    }

    /* ──────────────────────────────────────────
     * Relationships
     * ────────────────────────────────────────── */

    public function usageLogs(): HasMany
    {
        return $this->hasMany(MarketingUsageLog::class, 'resource_id');
    }
}

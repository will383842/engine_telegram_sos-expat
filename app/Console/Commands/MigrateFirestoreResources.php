<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MarketingResource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;

/**
 * Phase 3 — Migrate marketing resources from Firestore to PostgreSQL.
 *
 * Reads 12 Firestore collections and inserts them into the unified
 * `marketing_resources` table with proper role mapping, translation
 * merging, and type/category normalization.
 *
 * Usage:
 *   php artisan migrate:firestore-resources              # dry-run (default)
 *   php artisan migrate:firestore-resources --execute     # actually insert
 *   php artisan migrate:firestore-resources --collection=chatter_resources  # single collection
 *   php artisan migrate:firestore-resources --purge       # delete all before insert
 */
class MigrateFirestoreResources extends Command
{
    protected $signature = 'migrate:firestore-resources
        {--execute : Actually insert into PostgreSQL (default is dry-run)}
        {--collection= : Migrate a single collection only}
        {--purge : Delete all existing marketing_resources before inserting}';

    protected $description = 'Migrate marketing resources from Firestore to PostgreSQL';

    /**
     * Map Firestore collections → target_roles + default category prefix.
     */
    private const COLLECTION_MAP = [
        'chatter_resources'           => ['roles' => ['chatter', 'captain'], 'source' => 'resource'],
        'chatter_resource_texts'      => ['roles' => ['chatter', 'captain'], 'source' => 'text'],
        'influencer_resources'        => ['roles' => ['influencer'],         'source' => 'resource'],
        'influencer_resource_texts'   => ['roles' => ['influencer'],         'source' => 'text'],
        'blogger_resources'           => ['roles' => ['blogger'],            'source' => 'resource'],
        'blogger_resource_texts'      => ['roles' => ['blogger'],            'source' => 'text'],
        'blogger_articles'            => ['roles' => ['blogger'],            'source' => 'article'],
        'blogger_guide_templates'     => ['roles' => ['blogger'],            'source' => 'guide_template'],
        'blogger_guide_copy_texts'    => ['roles' => ['blogger'],            'source' => 'guide_copy'],
        'blogger_guide_best_practices'=> ['roles' => ['blogger'],            'source' => 'guide_practice'],
        'group_admin_resources'       => ['roles' => ['group_admin'],        'source' => 'resource'],
        'group_admin_posts'           => ['roles' => ['group_admin'],        'source' => 'post'],
    ];

    /**
     * Map old Firestore types to new PostgreSQL types.
     */
    private const TYPE_MAP = [
        'logo'     => 'logo',
        'image'    => 'image',
        'banner'   => 'banner',
        'text'     => 'text',
        'template' => 'template',
        'article'  => 'article',
        'video'    => 'video',
        'document' => 'document',
        'widget'   => 'widget',
        // Old Firestore types → new mapping
        'data'     => 'document',
        'photo'    => 'image',
        'bio'      => 'text',
        'quote'    => 'text',
    ];

    /**
     * Map old category values to valid PostgreSQL categories.
     */
    private const CATEGORY_MAP = [
        // Already valid
        'sos_expat'        => 'sos_expat',
        'ulixai'           => 'ulixai',
        'founder'          => 'founder',
        'promotional'      => 'promotional',
        'social_media'     => 'social_media',
        'templates'        => 'templates',
        'seo'              => 'seo',
        'recruitment'      => 'recruitment',
        'pinned_posts'     => 'pinned_posts',
        'cover_banners'    => 'cover_banners',
        'post_images'      => 'post_images',
        'story_images'     => 'story_images',
        'badges'           => 'badges',
        'welcome_messages' => 'welcome_messages',
        'partner_kit'      => 'partner_kit',
        // Blogger article categories → mapped to seo
        'how_to'           => 'seo',
        'comparison'       => 'seo',
        'testimonial'      => 'templates',
        'news'             => 'seo',
        // Blogger guide categories
        'intro'            => 'templates',
        'cta'              => 'templates',
        'feature'          => 'templates',
        'benefit'          => 'templates',
        'conclusion'       => 'templates',
        'writing'          => 'seo',
        'promotion'        => 'promotional',
        'conversion'       => 'seo',
        'monetization'     => 'seo',
        // GroupAdmin post categories
        'announcement'     => 'pinned_posts',
        'reminder'         => 'pinned_posts',
        'qa'               => 'pinned_posts',
        'emergency'        => 'pinned_posts',
        'seasonal'         => 'pinned_posts',
    ];

    private int $created = 0;
    private int $skipped = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $isDryRun = !$this->option('execute');
        $singleCollection = $this->option('collection');

        $this->info($isDryRun
            ? '🔍 DRY RUN — no data will be written. Use --execute to insert.'
            : '🚀 EXECUTE MODE — data will be inserted into PostgreSQL.');

        $firestore = Firebase::firestore()->database();

        if ($this->option('purge') && !$isDryRun) {
            $count = MarketingResource::count();
            if ($count > 0 && $this->confirm("Delete all {$count} existing marketing_resources?")) {
                MarketingResource::truncate();
                $this->warn("Purged {$count} records.");
            }
        }

        $collections = $singleCollection
            ? [$singleCollection => self::COLLECTION_MAP[$singleCollection] ?? null]
            : self::COLLECTION_MAP;

        foreach ($collections as $collectionName => $config) {
            if (!$config) {
                $this->error("Unknown collection: {$collectionName}");
                continue;
            }

            $this->newLine();
            $this->info("━━━ {$collectionName} ━━━");

            try {
                $documents = $firestore->collection($collectionName)->documents();
            } catch (\Throwable $e) {
                $this->error("  Failed to read collection: {$e->getMessage()}");
                $this->errors++;
                continue;
            }

            $count = 0;
            foreach ($documents as $doc) {
                if (!$doc->exists()) {
                    continue;
                }

                $data = $doc->data();
                $count++;

                try {
                    $mapped = $this->mapDocument($data, $config, $collectionName);

                    if ($isDryRun) {
                        $this->line("  [{$count}] " . ($mapped['name']['en'] ?? $mapped['name']['fr'] ?? '?')
                            . " → cat:{$mapped['category']} type:{$mapped['type']}"
                            . " roles:" . implode(',', $mapped['target_roles']));
                    } else {
                        MarketingResource::create($mapped);
                        $this->created++;
                    }
                } catch (\Throwable $e) {
                    $this->error("  [{$count}] Error mapping doc {$doc->id()}: {$e->getMessage()}");
                    $this->errors++;
                }
            }

            $this->info("  → {$count} documents found" . ($isDryRun ? ' (dry-run)' : ''));
        }

        $this->newLine();
        $this->info('━━━ Summary ━━━');
        $this->info("  Created: {$this->created}");
        $this->info("  Skipped: {$this->skipped}");
        $this->info("  Errors:  {$this->errors}");

        if ($isDryRun) {
            $this->warn('  → Dry-run complete. Run with --execute to insert.');
        }

        return self::SUCCESS;
    }

    /**
     * Map a Firestore document to the unified marketing_resources schema.
     */
    private function mapDocument(array $data, array $config, string $collection): array
    {
        $source = $config['source'];
        $roles = $config['roles'];

        // ── Name (merge default + translations) ──
        $name = $this->mergeTranslations(
            $data['name'] ?? $data['title'] ?? '',
            $data['nameTranslations'] ?? $data['titleTranslations'] ?? []
        );

        // ── Description ──
        $description = $this->mergeTranslations(
            $data['description'] ?? '',
            $data['descriptionTranslations'] ?? []
        );

        // ── Content ──
        $content = $this->mergeTranslations(
            $data['content'] ?? '',
            $data['contentTranslations'] ?? []
        );

        // ── Category ──
        $rawCategory = $data['category'] ?? 'sos_expat';
        $category = self::CATEGORY_MAP[$rawCategory] ?? $this->inferCategory($source, $rawCategory);

        // ── Type ──
        $rawType = $data['type'] ?? $this->inferType($source);
        $type = self::TYPE_MAP[$rawType] ?? $this->inferType($source);

        // ── File handling ──
        // Store Firebase Storage URLs as-is; toTranslated() handles absolute URLs
        $fileUrl = $data['fileUrl'] ?? $data['file_url'] ?? null;
        $thumbnailUrl = $data['thumbnailUrl'] ?? $data['thumbnail_url'] ?? $data['previewUrl'] ?? null;

        // ── Placeholders ──
        $placeholders = $data['placeholders'] ?? null;
        if (is_array($placeholders) && count($placeholders) === 0) {
            $placeholders = null;
        }

        // ── SEO keywords ──
        $seoKeywords = $data['seoKeywords'] ?? $data['seo_keywords'] ?? null;
        if (is_array($seoKeywords) && count($seoKeywords) === 0) {
            $seoKeywords = null;
        }

        return [
            'target_roles'   => $roles,
            'category'       => $category,
            'type'           => $type,
            'name'           => $name,
            'description'    => $this->isEmptyTranslation($description) ? null : $description,
            'content'        => $this->isEmptyTranslation($content) ? null : $content,
            'file_path'      => $fileUrl,
            'thumbnail_path' => $thumbnailUrl,
            'file_format'    => $data['fileFormat'] ?? $data['file_format'] ?? null,
            'file_size'      => $data['fileSize'] ?? $data['file_size'] ?? 0,
            'placeholders'   => $placeholders,
            'seo_keywords'   => $seoKeywords,
            'word_count'     => $data['estimatedWordCount'] ?? $data['word_count'] ?? $data['recommendedWordCount'] ?? null,
            'is_active'      => $data['isActive'] ?? $data['is_active'] ?? true,
            'sort_order'     => $data['order'] ?? $data['sort_order'] ?? 0,
            'download_count' => $data['downloadCount'] ?? $data['download_count'] ?? 0,
            'copy_count'     => $data['copyCount'] ?? $data['copy_count'] ?? $data['usageCount'] ?? 0,
        ];
    }

    /**
     * Merge a default string + translations map into a single jsonb-ready array.
     *
     * Firestore pattern: name="English default", nameTranslations={fr:"Français", en:"English", ...}
     * PostgreSQL pattern: name={"fr":"Français","en":"English",...}
     */
    private function mergeTranslations(mixed $defaultValue, mixed $translations): array
    {
        $result = [];

        // If translations is already a map, use it as base
        if (is_array($translations)) {
            foreach ($translations as $lang => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $result[$lang] = $value;
                }
            }
        }

        // If default value exists and 'en' isn't set, treat default as English
        if (is_string($defaultValue) && trim($defaultValue) !== '' && !isset($result['en'])) {
            $result['en'] = $defaultValue;
        }

        return $result;
    }

    /**
     * Check if a translation array is effectively empty.
     */
    private function isEmptyTranslation(array $translations): bool
    {
        return count($translations) === 0;
    }

    /**
     * Infer category from source type when no explicit category in Firestore.
     */
    private function inferCategory(string $source, string $rawCategory): string
    {
        return match ($source) {
            'article'        => 'seo',
            'guide_template' => 'templates',
            'guide_copy'     => 'templates',
            'guide_practice' => 'seo',
            'post'           => 'pinned_posts',
            default          => 'sos_expat', // safe fallback
        };
    }

    /**
     * Infer resource type from source when Firestore doc has no type field.
     */
    private function inferType(string $source): string
    {
        return match ($source) {
            'text'           => 'text',
            'article'        => 'article',
            'guide_template' => 'template',
            'guide_copy'     => 'text',
            'guide_practice' => 'document',
            'post'           => 'template',
            'resource'       => 'image', // default for generic resources
            default          => 'text',
        };
    }
}

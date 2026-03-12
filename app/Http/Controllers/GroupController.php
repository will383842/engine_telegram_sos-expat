<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelegramGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    /* ------------------------------------------------------------------
     * Constants
     * ---------------------------------------------------------------- */

    private const CONTINENTS = [
        'AF' => ['name' => 'Afrique', 'emoji' => "\u{1F30D}"],
        'AS' => ['name' => 'Asie', 'emoji' => "\u{1F30F}"],
        'EU' => ['name' => 'Europe', 'emoji' => "\u{1F30D}"],
        'NA' => ['name' => 'Amerique Nord', 'emoji' => "\u{1F30E}"],
        'SA' => ['name' => 'Amerique Sud', 'emoji' => "\u{1F30E}"],
        'OC' => ['name' => 'Oceanie', 'emoji' => "\u{1F30F}"],
        'ME' => ['name' => 'Moyen-Orient', 'emoji' => "\u{1F30D}"],
    ];

    private const LANGUAGE_FLAGS = [
        'fr' => "\u{1F1EB}\u{1F1F7}",
        'en' => "\u{1F1EC}\u{1F1E7}",
        'es' => "\u{1F1EA}\u{1F1F8}",
        'pt' => "\u{1F1E7}\u{1F1F7}",
        'de' => "\u{1F1E9}\u{1F1EA}",
        'ru' => "\u{1F1F7}\u{1F1FA}",
        'ar' => "\u{1F1F8}\u{1F1E6}",
        'zh' => "\u{1F1E8}\u{1F1F3}",
        'hi' => "\u{1F1EE}\u{1F1F3}",
    ];

    private const LANGUAGE_NAMES = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'pt' => 'Português',
        'de' => 'Deutsch',
        'ru' => 'Русский',
        'ar' => 'العربية',
        'zh' => '中文',
        'hi' => 'हिन्दी',
    ];

    private const ROLE_LABELS = [
        'chatter'    => 'Chatters',
        'influencer' => 'Influencers',
        'blogger'    => 'Bloggers',
        'groupAdmin' => 'Group Admins',
        'client'     => 'Clients',
        'lawyer'     => 'Lawyers',
        'expat'      => 'Expats',
    ];

    /* ------------------------------------------------------------------
     * CRUD
     * ---------------------------------------------------------------- */

    /**
     * GET /api/admin/groups
     *
     * List all groups, optionally filtered by ?role=
     */
    public function index(Request $request): JsonResponse
    {
        $query = TelegramGroup::query()->orderBy('role')->orderBy('type')->orderBy('language');

        if ($request->has('role')) {
            $query->forRole($request->input('role'));
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    /**
     * POST /api/admin/groups
     *
     * Create a new group.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug'           => 'required|string|max:128|unique:telegram_groups,slug',
            'name'           => 'required|string|max:255',
            'link'           => 'nullable|string|max:512',
            'language'       => 'required|string|max:5',
            'role'           => 'required|string|max:32',
            'type'           => 'required|string|in:continent,language',
            'continent_code' => 'nullable|string|max:2',
            'enabled'        => 'nullable|boolean',
            'managers'       => 'nullable|array',
        ]);

        $group = TelegramGroup::create([
            'slug'           => $validated['slug'],
            'name'           => $validated['name'],
            'link'           => $validated['link'] ?? '',
            'language'       => $validated['language'],
            'role'           => $validated['role'],
            'type'           => $validated['type'],
            'continent_code' => $validated['continent_code'] ?? null,
            'enabled'        => $validated['enabled'] ?? false,
            'managers'       => $validated['managers'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $group,
        ], 201);
    }

    /**
     * PUT /api/admin/groups/{id}
     *
     * Update an existing group.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $group = TelegramGroup::findOrFail($id);

        $validated = $request->validate([
            'slug'           => 'sometimes|string|max:128|unique:telegram_groups,slug,' . $id,
            'name'           => 'sometimes|string|max:255',
            'link'           => 'nullable|string|max:512',
            'language'       => 'sometimes|string|max:5',
            'role'           => 'sometimes|string|max:32',
            'type'           => 'sometimes|string|in:continent,language',
            'continent_code' => 'nullable|string|max:2',
            'enabled'        => 'nullable|boolean',
            'managers'       => 'nullable|array',
        ]);

        $group->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $group->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/groups/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $group = TelegramGroup::findOrFail($id);
        $group->delete();

        return response()->json([
            'success' => true,
            'message' => "Group '{$group->slug}' deleted.",
        ]);
    }

    /* ------------------------------------------------------------------
     * Manager management
     * ---------------------------------------------------------------- */

    /**
     * POST /api/admin/groups/{id}/managers
     *
     * Add a manager to a group.
     */
    public function addManager(Request $request, int $id): JsonResponse
    {
        $group = TelegramGroup::findOrFail($id);

        $validated = $request->validate([
            'displayName' => 'required|string|max:128',
            'email'       => 'nullable|string|email|max:255',
            'phone'       => 'nullable|string|max:20',
        ]);

        $managers = $group->managers ?? [];

        $manager = [
            'uid'         => 'manual_' . Str::random(10),
            'displayName' => $validated['displayName'],
            'email'       => $validated['email'] ?? '',
            'phone'       => $validated['phone'] ?? '',
            'assignedAt'  => Carbon::now()->toIso8601String(),
            'assignedBy'  => $request->input('firebase_uid', 'admin'),
        ];

        $managers[] = $manager;
        $group->managers = $managers;
        $group->save();

        return response()->json([
            'success' => true,
            'data'    => $manager,
            'group'   => $group->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/groups/{id}/managers/{managerId}
     *
     * Remove a manager from a group.
     */
    public function removeManager(int $id, string $managerId): JsonResponse
    {
        $group = TelegramGroup::findOrFail($id);

        $managers = $group->managers ?? [];
        $filtered = array_values(array_filter($managers, fn (array $m) => $m['uid'] !== $managerId));

        if (count($filtered) === count($managers)) {
            return response()->json([
                'success' => false,
                'error'   => "Manager '{$managerId}' not found in group.",
            ], 404);
        }

        $group->managers = $filtered;
        $group->save();

        return response()->json([
            'success' => true,
            'message' => "Manager '{$managerId}' removed.",
            'group'   => $group->fresh(),
        ]);
    }

    /* ------------------------------------------------------------------
     * Seed & generation
     * ---------------------------------------------------------------- */

    /**
     * POST /api/admin/groups/seed
     *
     * Seed all 68 default groups.
     */
    public function seed(): JsonResponse
    {
        $created = 0;
        $skipped = 0;

        // 1. Chatters: 7 continents × 2 languages (FR + EN) = 14 continent groups
        foreach (self::CONTINENTS as $code => $continent) {
            foreach (['fr', 'en'] as $lang) {
                $slug = "chatter_continent_{$code}_{$lang}";

                if (TelegramGroup::where('slug', $slug)->exists()) {
                    $skipped++;
                    continue;
                }

                $flag = self::LANGUAGE_FLAGS[$lang];
                TelegramGroup::create([
                    'slug'           => $slug,
                    'name'           => "Chatters {$continent['emoji']} {$continent['name']} {$flag}",
                    'link'           => '',
                    'language'       => $lang,
                    'role'           => 'chatter',
                    'type'           => 'continent',
                    'continent_code' => $code,
                    'enabled'        => true,
                    'managers'       => [],
                ]);
                $created++;
            }
        }

        // 2. Language groups for: influencer, blogger, groupAdmin, client (enabled)
        foreach (['influencer', 'blogger', 'groupAdmin', 'client'] as $role) {
            foreach (self::LANGUAGE_FLAGS as $lang => $flag) {
                $slug = "{$role}_lang_{$lang}";

                if (TelegramGroup::where('slug', $slug)->exists()) {
                    $skipped++;
                    continue;
                }

                $label = self::ROLE_LABELS[$role] ?? ucfirst($role);
                $langName = self::LANGUAGE_NAMES[$lang] ?? $lang;

                TelegramGroup::create([
                    'slug'           => $slug,
                    'name'           => "{$label} {$flag} {$langName}",
                    'link'           => '',
                    'language'       => $lang,
                    'role'           => $role,
                    'type'           => 'language',
                    'continent_code' => null,
                    'enabled'        => true,
                    'managers'       => [],
                ]);
                $created++;
            }
        }

        // 3. Language groups for: lawyer, expat (disabled, no links)
        foreach (['lawyer', 'expat'] as $role) {
            foreach (self::LANGUAGE_FLAGS as $lang => $flag) {
                $slug = "{$role}_lang_{$lang}";

                if (TelegramGroup::where('slug', $slug)->exists()) {
                    $skipped++;
                    continue;
                }

                $label = self::ROLE_LABELS[$role] ?? ucfirst($role);
                $langName = self::LANGUAGE_NAMES[$lang] ?? $lang;

                TelegramGroup::create([
                    'slug'           => $slug,
                    'name'           => "{$label} {$flag} {$langName}",
                    'link'           => '',
                    'language'       => $lang,
                    'role'           => $role,
                    'type'           => 'language',
                    'continent_code' => null,
                    'enabled'        => false,
                    'managers'       => [],
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'total'   => TelegramGroup::count(),
        ]);
    }

    /**
     * POST /api/admin/groups/generate-continent
     *
     * Generate continent groups for a given role.
     * Body: { role: string, languages?: string[] }
     */
    public function generateContinent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role'        => 'required|string|max:32',
            'languages'   => 'nullable|array',
            'languages.*' => 'string|max:5',
        ]);

        $role = $validated['role'];
        $languages = $validated['languages'] ?? ['fr', 'en'];
        $label = self::ROLE_LABELS[$role] ?? ucfirst($role);
        $created = 0;

        foreach (self::CONTINENTS as $code => $continent) {
            foreach ($languages as $lang) {
                $slug = "{$role}_continent_{$code}_{$lang}";

                if (TelegramGroup::where('slug', $slug)->exists()) {
                    continue;
                }

                $flag = self::LANGUAGE_FLAGS[$lang] ?? '';

                TelegramGroup::create([
                    'slug'           => $slug,
                    'name'           => "{$label} {$continent['emoji']} {$continent['name']} {$flag}",
                    'link'           => '',
                    'language'       => $lang,
                    'role'           => $role,
                    'type'           => 'continent',
                    'continent_code' => $code,
                    'enabled'        => true,
                    'managers'       => [],
                ]);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'role'    => $role,
        ]);
    }

    /**
     * POST /api/admin/groups/generate-language
     *
     * Generate language groups for a given role.
     * Body: { role: string, enabled?: boolean }
     */
    public function generateLanguage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role'    => 'required|string|max:32',
            'enabled' => 'nullable|boolean',
        ]);

        $role = $validated['role'];
        $enabled = $validated['enabled'] ?? true;
        $label = self::ROLE_LABELS[$role] ?? ucfirst($role);
        $created = 0;

        foreach (self::LANGUAGE_FLAGS as $lang => $flag) {
            $slug = "{$role}_lang_{$lang}";

            if (TelegramGroup::where('slug', $slug)->exists()) {
                continue;
            }

            $langName = self::LANGUAGE_NAMES[$lang] ?? $lang;

            TelegramGroup::create([
                'slug'           => $slug,
                'name'           => "{$label} {$flag} {$langName}",
                'link'           => '',
                'language'       => $lang,
                'role'           => $role,
                'type'           => 'language',
                'continent_code' => null,
                'enabled'        => $enabled,
                'managers'       => [],
            ]);
            $created++;
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'role'    => $role,
        ]);
    }
}

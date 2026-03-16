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
     * All 68 Telegram invite links.
     * Key = slug, Value = invite link (empty string = not yet created).
     */
    private const SEED_LINKS = [
        // ── Chatters: 14 continent groups (FR/EN × 7 continents) ──
        'chatter_continent_AF_fr' => 'https://t.me/+QS1P7KIAzcAxZDg0',
        'chatter_continent_AF_en' => 'https://t.me/+FLnbGk4cLUVmNzdk',
        'chatter_continent_AS_fr' => 'https://t.me/+EN4DBiRyk3FiNDI0',
        'chatter_continent_AS_en' => 'https://t.me/+2nh6iyO2iOg5NDBk',
        'chatter_continent_EU_fr' => 'https://t.me/+fjLzsepnXs4wZWY0',
        'chatter_continent_EU_en' => 'https://t.me/+5-vmAdu-2tdiZTg8',
        'chatter_continent_NA_fr' => 'https://t.me/+Pduz1TVGTuhjYjA0',
        'chatter_continent_NA_en' => 'https://t.me/+gHDGVFKYdIViYzI0',
        'chatter_continent_SA_fr' => 'https://t.me/+xfwsxaZFE14wZTQ0',
        'chatter_continent_SA_en' => 'https://t.me/+DfY52ks0vcg3YTc0',
        'chatter_continent_OC_fr' => 'https://t.me/+CiokZ3rKCzU2MzFk',
        'chatter_continent_OC_en' => 'https://t.me/+aCZMkDMrYjY0NDY0',
        'chatter_continent_ME_fr' => 'https://t.me/+bZlABDWbIfBmM2M0',
        'chatter_continent_ME_en' => 'https://t.me/+M2XdoMdjlhBmM2Q0',
        // ── Influencers: 9 language groups ──
        'influencer_lang_fr' => 'https://t.me/+zlKPgGzyyuk5YWJk',
        'influencer_lang_en' => 'https://t.me/+BeNd30OjKKNlNWQ0',
        'influencer_lang_es' => 'https://t.me/+llpjaM5pGJljY2Fk',
        'influencer_lang_pt' => 'https://t.me/+qaHxU4-jUIg4Mzc8',
        'influencer_lang_de' => 'https://t.me/+GWMZVknbn7FmNDQ0',
        'influencer_lang_ru' => 'https://t.me/+U3FfjEd2EE40MzM0',
        'influencer_lang_ar' => 'https://t.me/+bes_k8Y4rOs5OGQ0',
        'influencer_lang_zh' => 'https://t.me/+QLjQVp6765A1ZGRk',
        'influencer_lang_hi' => 'https://t.me/+CT7-Ikat_dM5YTE0',
        // ── Bloggers: 9 language groups ──
        'blogger_lang_fr' => 'https://t.me/+OU9w5x4RPuA2YjM0',
        'blogger_lang_en' => 'https://t.me/+QkSfgVw9WTFjZjZk',
        'blogger_lang_es' => 'https://t.me/+sZBC0xzxeds3Y2Q0',
        'blogger_lang_pt' => 'https://t.me/+TeKa7RK3nkhmOGY0',
        'blogger_lang_de' => 'https://t.me/+huSf3QXSg1kyZGY0',
        'blogger_lang_ru' => 'https://t.me/+vdMx-xVFT6Y5Yzk0',
        'blogger_lang_ar' => 'https://t.me/+8UWvyUiECYlhZTc0',
        'blogger_lang_zh' => 'https://t.me/+JhMUWfavWTY3ODc8',
        'blogger_lang_hi' => 'https://t.me/+r8LIxdAm-ZoxMDBk',
        // ── Group Admins: 9 language groups ──
        'groupAdmin_lang_fr' => 'https://t.me/+i_5tGY2azu1mMTg8',
        'groupAdmin_lang_en' => 'https://t.me/+lFFm_NPGOnI5N2Jk',
        'groupAdmin_lang_es' => 'https://t.me/+gBbY-cjTqlU0ODE0',
        'groupAdmin_lang_pt' => 'https://t.me/+hrfSF5ICzCQyZjJk',
        'groupAdmin_lang_de' => 'https://t.me/+2CiWLV1_9E9lZDk0',
        'groupAdmin_lang_ru' => 'https://t.me/+okjF6z7rh4oxYjY0',
        'groupAdmin_lang_ar' => 'https://t.me/+Qowimt46reBjN2M0',
        'groupAdmin_lang_zh' => 'https://t.me/+vD11u5pCa781M2I0',
        'groupAdmin_lang_hi' => 'https://t.me/+PDietMVx76tkNjk0',
        // ── Clients: 9 language groups ──
        'client_lang_fr' => 'https://t.me/+llUUyPKDiZBhYzc0',
        'client_lang_en' => 'https://t.me/+qso-1ETa_G1jMzdk',
        'client_lang_es' => 'https://t.me/+-S7-7ErLhuc4Mzdk',
        'client_lang_pt' => 'https://t.me/+0Wj1G6UdvPE1NDFk',
        'client_lang_de' => 'https://t.me/+dMDNZ4-FjKxkNmNk',
        'client_lang_ru' => 'https://t.me/+4YKj7xcJgdQ3ZmM0',
        'client_lang_ar' => 'https://t.me/+ErlIyf_DpQFiNTA0',
        'client_lang_zh' => 'https://t.me/+s8_dHD09Ydw1ODFk',
        'client_lang_hi' => '',  // TODO: create group
        // ── Lawyers: 9 language groups ──
        'lawyer_lang_fr' => '',  // TODO: create group
        'lawyer_lang_en' => '',  // TODO: create group
        'lawyer_lang_es' => '',  // TODO: create group
        'lawyer_lang_pt' => '',  // TODO: create group
        'lawyer_lang_de' => '',  // TODO: create group
        'lawyer_lang_ru' => '',  // TODO: create group
        'lawyer_lang_ar' => '',  // TODO: create group
        'lawyer_lang_zh' => '',  // TODO: create group
        'lawyer_lang_hi' => '',  // TODO: create group
        // ── Expats: 9 language groups ──
        'expat_lang_fr' => '',  // TODO: create group
        'expat_lang_en' => '',  // TODO: create group
        'expat_lang_es' => '',  // TODO: create group
        'expat_lang_pt' => '',  // TODO: create group
        'expat_lang_de' => '',  // TODO: create group
        'expat_lang_ru' => '',  // TODO: create group
        'expat_lang_ar' => '',  // TODO: create group
        'expat_lang_zh' => '',  // TODO: create group
        'expat_lang_hi' => '',  // TODO: create group
    ];

    /**
     * POST /api/admin/groups/seed
     *
     * Seed all 68 default groups with pre-configured invite links.
     * Uses updateOrCreate so existing groups get their links updated.
     */
    public function seed(): JsonResponse
    {
        $created = 0;
        $updated = 0;

        // 1. Chatters: 7 continents × 2 languages (FR + EN) = 14 continent groups
        foreach (self::CONTINENTS as $code => $continent) {
            foreach (['fr', 'en'] as $lang) {
                $slug = "chatter_continent_{$code}_{$lang}";
                $flag = self::LANGUAGE_FLAGS[$lang];
                $link = self::SEED_LINKS[$slug] ?? '';

                $existed = TelegramGroup::where('slug', $slug)->exists();

                TelegramGroup::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name'           => "Chatters {$continent['emoji']} {$continent['name']} {$flag}",
                        'link'           => $link,
                        'language'       => $lang,
                        'role'           => 'chatter',
                        'type'           => 'continent',
                        'continent_code' => $code,
                        'enabled'        => $link !== '',
                        'managers'       => [],
                    ]
                );
                $existed ? $updated++ : $created++;
            }
        }

        // 2. Language groups for: influencer, blogger, groupAdmin, client, lawyer, expat
        foreach (['influencer', 'blogger', 'groupAdmin', 'client', 'lawyer', 'expat'] as $role) {
            foreach (self::LANGUAGE_FLAGS as $lang => $flag) {
                $slug = "{$role}_lang_{$lang}";
                $link = self::SEED_LINKS[$slug] ?? '';
                $label = self::ROLE_LABELS[$role] ?? ucfirst($role);
                $langName = self::LANGUAGE_NAMES[$lang] ?? $lang;

                $existed = TelegramGroup::where('slug', $slug)->exists();

                TelegramGroup::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name'           => "{$label} {$flag} {$langName}",
                        'link'           => $link,
                        'language'       => $lang,
                        'role'           => $role,
                        'type'           => 'language',
                        'continent_code' => null,
                        'enabled'        => $link !== '',
                        'managers'       => [],
                    ]
                );
                $existed ? $updated++ : $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'updated' => $updated,
            'total'   => TelegramGroup::count(),
            'with_links' => TelegramGroup::where('link', '!=', '')->count(),
            'without_links' => TelegramGroup::where('link', '')->count(),
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
                    'enabled'        => false,
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
        $enabled = $validated['enabled'] ?? false;
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

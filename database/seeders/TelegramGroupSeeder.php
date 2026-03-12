<?php

namespace Database\Seeders;

use App\Data\GeoData;
use App\Models\TelegramGroup;
use Illuminate\Database\Seeder;

/**
 * Seed Telegram groups — exact same structure as WhatsApp groups.
 *
 * Creates 68 groups:
 * - Chatters: 7 continents × 2 languages (FR/EN) = 14 continent groups
 * - 7 other roles × 9 languages = 63 language groups
 *   (influencer, blogger, group_admin, client, lawyer, expat, partner)
 *
 * All disabled by default, no links — admin activates and adds links.
 */
class TelegramGroupSeeder extends Seeder
{
    /** Roles that get continent-based groups (continent × FR/EN). */
    private const CONTINENT_ROLES = ['chatter'];

    /** Languages for continent groups (only FR/EN like WhatsApp). */
    private const CONTINENT_LANGUAGES = ['fr', 'en'];

    /** Roles that get language-only groups. */
    private const LANGUAGE_ROLES = [
        'influencer',
        'blogger',
        'group_admin',
        'client',
        'lawyer',
        'expat',
        'partner',
    ];

    public function run(): void
    {
        $created = 0;

        // ── Chatters: 7 continents × 2 languages (FR/EN) = 14 groups ──
        foreach (self::CONTINENT_ROLES as $role) {
            $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

            foreach (GeoData::CONTINENTS as $continentCode => $continent) {
                foreach (self::CONTINENT_LANGUAGES as $langCode) {
                    $lang = GeoData::LANGUAGES[$langCode];
                    $slug = "{$role}_{$continentCode}_{$langCode}";

                    TelegramGroup::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => "{$roleLabel} {$continent['emoji']} {$continent['name']} {$lang['flag']}",
                            'link' => '',
                            'language' => $langCode,
                            'role' => $role,
                            'type' => 'continent',
                            'continent_code' => $continentCode,
                            'enabled' => false,
                        ]
                    );
                    $created++;
                }
            }
        }

        // ── Other 6 roles: 9 languages each = 54 groups ──
        foreach (self::LANGUAGE_ROLES as $role) {
            $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

            foreach (GeoData::LANGUAGES as $langCode => $lang) {
                $slug = "{$role}_lang_{$langCode}";

                TelegramGroup::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => "{$roleLabel} {$lang['name']} {$lang['flag']}",
                        'link' => '',
                        'language' => $langCode,
                        'role' => $role,
                        'type' => 'language',
                        'continent_code' => null,
                        'enabled' => false,
                    ]
                );
                $created++;
            }
        }

        $this->command->info("Telegram groups seeded: {$created} groups (14 chatter continent + 63 language).");
    }
}

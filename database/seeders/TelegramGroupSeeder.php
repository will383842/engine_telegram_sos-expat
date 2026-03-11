<?php

namespace Database\Seeders;

use App\Data\GeoData;
use App\Models\TelegramGroup;
use Illuminate\Database\Seeder;

/**
 * Seed Telegram groups for all 10 roles.
 *
 * Creates:
 * - 10 roles × 7 continents × 9 languages = 630 continent groups
 * - 10 roles × 9 languages = 90 language fallback groups
 * - Total: 720 groups (all disabled, no links — admin enables and adds links)
 */
class TelegramGroupSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        foreach (GeoData::ROLES as $role) {
            $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

            // Continent groups (7 continents × 9 languages = 63 per role)
            foreach (GeoData::CONTINENTS as $continentCode => $continent) {
                foreach (GeoData::LANGUAGES as $langCode => $lang) {
                    $slug = "{$role}_continent_{$continentCode}_{$langCode}";

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

            // Language fallback groups (9 per role)
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

        $this->command->info("Telegram groups seeded: {$created} groups for " . count(GeoData::ROLES) . " roles.");
    }
}

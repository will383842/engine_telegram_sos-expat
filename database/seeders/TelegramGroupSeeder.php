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
 * - 6 other roles × 9 languages = 54 language groups
 *   (influencer, blogger, groupAdmin, client, lawyer, expat)
 *
 * Uses updateOrCreate — safe to re-run to update links.
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
        'groupAdmin',
        'client',
        'lawyer',
        'expat',
    ];

    /**
     * All 68 Telegram invite links.
     * Key = slug, Value = invite link (empty string = not yet created).
     * Updated 2026-03-12.
     */
    private const LINKS = [
        // Chatters continent
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
        // Influencers
        'influencer_lang_fr' => 'https://t.me/+zlKPgGzyyuk5YWJk',
        'influencer_lang_en' => 'https://t.me/+BeNd30OjKKNlNWQ0',
        'influencer_lang_es' => 'https://t.me/+llpjaM5pGJljY2Fk',
        'influencer_lang_pt' => 'https://t.me/+qaHxU4-jUIg4Mzc8',
        'influencer_lang_de' => 'https://t.me/+GWMZVknbn7FmNDQ0',
        'influencer_lang_ru' => 'https://t.me/+U3FfjEd2EE40MzM0',
        'influencer_lang_ar' => 'https://t.me/+bes_k8Y4rOs5OGQ0',
        'influencer_lang_zh' => 'https://t.me/+QLjQVp6765A1ZGRk',
        'influencer_lang_hi' => 'https://t.me/+CT7-Ikat_dM5YTE0',
        // Bloggers
        'blogger_lang_fr' => 'https://t.me/+OU9w5x4RPuA2YjM0',
        'blogger_lang_en' => 'https://t.me/+QkSfgVw9WTFjZjZk',
        'blogger_lang_es' => 'https://t.me/+sZBC0xzxeds3Y2Q0',
        'blogger_lang_pt' => 'https://t.me/+TeKa7RK3nkhmOGY0',
        'blogger_lang_de' => 'https://t.me/+huSf3QXSg1kyZGY0',
        'blogger_lang_ru' => 'https://t.me/+vdMx-xVFT6Y5Yzk0',
        'blogger_lang_ar' => 'https://t.me/+8UWvyUiECYlhZTc0',
        'blogger_lang_zh' => 'https://t.me/+JhMUWfavWTY3ODc8',
        'blogger_lang_hi' => 'https://t.me/+r8LIxdAm-ZoxMDBk',
        // Group Admins
        'groupAdmin_lang_fr' => 'https://t.me/+i_5tGY2azu1mMTg8',
        'groupAdmin_lang_en' => 'https://t.me/+lFFm_NPGOnI5N2Jk',
        'groupAdmin_lang_es' => 'https://t.me/+gBbY-cjTqlU0ODE0',
        'groupAdmin_lang_pt' => 'https://t.me/+hrfSF5ICzCQyZjJk',
        'groupAdmin_lang_de' => 'https://t.me/+2CiWLV1_9E9lZDk0',
        'groupAdmin_lang_ru' => 'https://t.me/+okjF6z7rh4oxYjY0',
        'groupAdmin_lang_ar' => 'https://t.me/+Qowimt46reBjN2M0',
        'groupAdmin_lang_zh' => 'https://t.me/+vD11u5pCa781M2I0',
        'groupAdmin_lang_hi' => 'https://t.me/+PDietMVx76tkNjk0',
        // Clients
        'client_lang_fr' => 'https://t.me/+llUUyPKDiZBhYzc0',
        'client_lang_en' => 'https://t.me/+qso-1ETa_G1jMzdk',
        'client_lang_es' => 'https://t.me/+-S7-7ErLhuc4Mzdk',
        'client_lang_pt' => 'https://t.me/+0Wj1G6UdvPE1NDFk',
        'client_lang_de' => 'https://t.me/+dMDNZ4-FjKxkNmNk',
        'client_lang_ru' => 'https://t.me/+4YKj7xcJgdQ3ZmM0',
        'client_lang_ar' => 'https://t.me/+ErlIyf_DpQFiNTA0',
        'client_lang_zh' => 'https://t.me/+s8_dHD09Ydw1ODFk',
        'client_lang_hi' => '',  // TODO
        // Lawyers
        'lawyer_lang_fr' => '',  // TODO
        'lawyer_lang_en' => '',  // TODO
        'lawyer_lang_es' => '',  // TODO
        'lawyer_lang_pt' => '',  // TODO
        'lawyer_lang_de' => '',  // TODO
        'lawyer_lang_ru' => '',  // TODO
        'lawyer_lang_ar' => '',  // TODO
        'lawyer_lang_zh' => '',  // TODO
        'lawyer_lang_hi' => '',  // TODO
        // Expats
        'expat_lang_fr' => '',  // TODO
        'expat_lang_en' => '',  // TODO
        'expat_lang_es' => '',  // TODO
        'expat_lang_pt' => '',  // TODO
        'expat_lang_de' => '',  // TODO
        'expat_lang_ru' => '',  // TODO
        'expat_lang_ar' => '',  // TODO
        'expat_lang_zh' => '',  // TODO
        'expat_lang_hi' => '',  // TODO
    ];

    public function run(): void
    {
        $created = 0;
        $updated = 0;

        // ── Chatters: 7 continents × 2 languages (FR/EN) = 14 groups ──
        foreach (self::CONTINENT_ROLES as $role) {
            $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

            foreach (GeoData::CONTINENTS as $continentCode => $continent) {
                foreach (self::CONTINENT_LANGUAGES as $langCode) {
                    $lang = GeoData::LANGUAGES[$langCode];
                    $slug = "{$role}_continent_{$continentCode}_{$langCode}";
                    $link = self::LINKS[$slug] ?? '';

                    $existed = TelegramGroup::where('slug', $slug)->exists();

                    TelegramGroup::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => "{$roleLabel} {$continent['emoji']} {$continent['name']} {$lang['flag']}",
                            'link' => $link,
                            'language' => $langCode,
                            'role' => $role,
                            'type' => 'continent',
                            'continent_code' => $continentCode,
                            'enabled' => $link !== '',
                        ]
                    );
                    $existed ? $updated++ : $created++;
                }
            }
        }

        // ── Other 6 roles: 9 languages each = 54 groups ──
        foreach (self::LANGUAGE_ROLES as $role) {
            $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

            foreach (GeoData::LANGUAGES as $langCode => $lang) {
                $slug = "{$role}_lang_{$langCode}";
                $link = self::LINKS[$slug] ?? '';

                $existed = TelegramGroup::where('slug', $slug)->exists();

                TelegramGroup::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => "{$roleLabel} {$lang['name']} {$lang['flag']}",
                        'link' => $link,
                        'language' => $langCode,
                        'role' => $role,
                        'type' => 'language',
                        'continent_code' => null,
                        'enabled' => $link !== '',
                    ]
                );
                $existed ? $updated++ : $created++;
            }
        }

        $withLinks = TelegramGroup::where('link', '!=', '')->count();
        $this->command->info("Telegram groups seeded: {$created} created, {$updated} updated. {$withLinks}/68 with links.");
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\GeoData;
use App\Models\TelegramGroup;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Group Service — find the right group for a user.
 *
 * 6-level resolution (same logic as WhatsApp groups):
 *   1. Continent + exact user language
 *   2. Continent + language inferred from country
 *   3. Language fallback group (user language)
 *   4. Language fallback group (country language)
 *   5. Default group for the role (first enabled)
 *   6. Any enabled group for the role
 */
class TelegramGroupService
{
    /**
     * Find the best Telegram group for a user based on role, language, and country.
     */
    public function findGroupForUser(string $role, string $language, string $country): ?TelegramGroup
    {
        $lang = GeoData::normalizeLanguage($language);
        $upperCountry = strtoupper($country);
        $continent = GeoData::continentForCountry($upperCountry);
        $countryLang = GeoData::languageForCountry($upperCountry);

        // Get all enabled groups for this role
        $groups = TelegramGroup::where('role', $role)
            ->where('enabled', true)
            ->where('link', '!=', '')
            ->get();

        if ($groups->isEmpty()) {
            return null;
        }

        // 1. Continent + exact user language
        if ($continent) {
            $match = $groups->first(fn (TelegramGroup $g) =>
                $g->type === 'continent' && $g->continent_code === $continent && $g->language === $lang
            );
            if ($match) return $match;
        }

        // 2. Continent + language inferred from country
        if ($continent && $countryLang && $countryLang !== $lang) {
            $match = $groups->first(fn (TelegramGroup $g) =>
                $g->type === 'continent' && $g->continent_code === $continent && $g->language === $countryLang
            );
            if ($match) return $match;
        }

        // 3. Language fallback (user language)
        $match = $groups->first(fn (TelegramGroup $g) =>
            $g->type === 'language' && $g->language === $lang
        );
        if ($match) return $match;

        // 4. Language fallback (country language)
        if ($countryLang && $countryLang !== $lang) {
            $match = $groups->first(fn (TelegramGroup $g) =>
                $g->type === 'language' && $g->language === $countryLang
            );
            if ($match) return $match;
        }

        // 5 & 6. Any enabled group for the role
        return $groups->first();
    }

    /**
     * Generate the welcome message with group link for a user who just linked.
     */
    public function buildWelcomeMessage(string $firstName, string $role, ?TelegramGroup $group): string
    {
        $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

        $message = "🎉 <b>Bienvenue {$firstName} !</b>\n\n"
            . "Votre compte Telegram a été lié avec succès à SOS-Expat.\n"
            . "Rôle : <b>{$roleLabel}</b>\n\n";

        if ($group && $group->hasLink()) {
            $message .= "📱 <b>Rejoignez votre groupe Telegram :</b>\n"
                . "<a href=\"{$group->link}\">{$group->name}</a>\n\n"
                . "Ce groupe vous permet d'échanger avec la communauté SOS-Expat !\n";
        }

        $message .= "\nCommandes disponibles :\n"
            . "/balance — Voir votre solde\n"
            . "/stats — Voir vos statistiques\n"
            . "/help — Aide";

        return $message;
    }

    /**
     * Build message for expired/invalid onboarding code.
     */
    public function buildErrorMessage(string $errorType): string
    {
        return match ($errorType) {
            'expired' => "⏰ Ce lien d'inscription a expiré.\n\nVeuillez générer un nouveau lien depuis votre dashboard SOS-Expat.",
            'invalid' => "❌ Ce lien d'inscription n'est pas valide.\n\nVeuillez utiliser le lien depuis votre dashboard SOS-Expat.",
            'already_linked' => "✅ Votre compte Telegram est déjà lié à SOS-Expat !\n\nUtilisez /balance ou /stats pour voir vos informations.",
            default => "❌ Une erreur est survenue. Veuillez réessayer depuis votre dashboard SOS-Expat.",
        };
    }
}

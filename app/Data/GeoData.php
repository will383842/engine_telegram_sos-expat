<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Geographic data: country→language, country→continent mappings.
 * Shared across Telegram and WhatsApp group resolution.
 * Exact same data as sos/src/whatsapp-groups/types.ts
 */
class GeoData
{
    /** All system roles */
    public const ROLES = [
        'chatter', 'influencer', 'blogger', 'group_admin',
        'client', 'lawyer', 'expat', 'partner',
        'captain', 'captain_chatter', 'affiliate',
    ];

    /** Role labels */
    public const ROLE_LABELS = [
        'chatter'         => 'Chatter',
        'influencer'      => 'Influencer',
        'blogger'         => 'Blogger',
        'group_admin'     => 'Group Admin',
        'groupAdmin'      => 'Group Admin',
        'client'          => 'Client',
        'lawyer'          => 'Avocat',
        'expat'           => 'Expatrié Aidant',
        'partner'         => 'Partenaire',
        'captain'         => 'Capitaine',
        'captain_chatter' => 'Capitaine Chatter',
        'affiliate'       => 'Affilié',
    ];

    /** 7 continents with emoji */
    public const CONTINENTS = [
        'AF' => ['name' => 'Afrique',           'emoji' => '🌍'],
        'AS' => ['name' => 'Asie',              'emoji' => '🌏'],
        'EU' => ['name' => 'Europe',            'emoji' => '🇪🇺'],
        'NA' => ['name' => 'Amérique du Nord',  'emoji' => '🌎'],
        'SA' => ['name' => 'Amérique du Sud',   'emoji' => '🌎'],
        'OC' => ['name' => 'Océanie',           'emoji' => '🌏'],
        'ME' => ['name' => 'Moyen-Orient',      'emoji' => '🕌'],
    ];

    /** 9 languages with flag */
    public const LANGUAGES = [
        'fr' => ['name' => 'Français',    'flag' => '🇫🇷'],
        'en' => ['name' => 'English',     'flag' => '🇬🇧'],
        'es' => ['name' => 'Español',     'flag' => '🇪🇸'],
        'pt' => ['name' => 'Português',   'flag' => '🇧🇷'],
        'de' => ['name' => 'Deutsch',     'flag' => '🇩🇪'],
        'ru' => ['name' => 'Russkiy',     'flag' => '🇷🇺'],
        'ar' => ['name' => 'Al-Arabiyya', 'flag' => '🇸🇦'],
        'zh' => ['name' => 'Zhongwen',    'flag' => '🇨🇳'],
        'hi' => ['name' => 'Hindi',       'flag' => '🇮🇳'],
    ];

    /** Country code → default language */
    public const COUNTRY_TO_LANGUAGE = [
        // Francophone
        'FR' => 'fr', 'BE' => 'fr', 'CH' => 'fr', 'CA' => 'fr', 'LU' => 'fr',
        'MA' => 'fr', 'TN' => 'fr', 'DZ' => 'fr', 'SN' => 'fr', 'CM' => 'fr',
        'CI' => 'fr', 'CD' => 'fr', 'CG' => 'fr', 'MG' => 'fr', 'ML' => 'fr',
        'BF' => 'fr', 'NE' => 'fr', 'TD' => 'fr', 'GN' => 'fr', 'BJ' => 'fr',
        'TG' => 'fr', 'GA' => 'fr', 'DJ' => 'fr', 'KM' => 'fr', 'CF' => 'fr',
        'RW' => 'fr', 'BI' => 'fr', 'MU' => 'fr', 'SC' => 'fr', 'HT' => 'fr',
        'MC' => 'fr', 'GQ' => 'fr',
        // Anglophone
        'US' => 'en', 'GB' => 'en', 'AU' => 'en', 'NZ' => 'en', 'IE' => 'en',
        'NG' => 'en', 'GH' => 'en', 'KE' => 'en', 'ZA' => 'en', 'TZ' => 'en',
        'UG' => 'en', 'ZW' => 'en', 'ZM' => 'en', 'BW' => 'en', 'NA' => 'en',
        'JM' => 'en', 'TT' => 'en', 'SG' => 'en', 'PH' => 'en', 'UA' => 'en',
        'PK' => 'en', 'BD' => 'en', 'LK' => 'en', 'MY' => 'en', 'SL' => 'en',
        'LR' => 'en', 'MW' => 'en', 'GM' => 'en', 'FJ' => 'en', 'MT' => 'en',
        'TH' => 'en', 'VN' => 'en', 'KH' => 'en', 'MM' => 'en', 'LA' => 'en',
        // Hispanophone
        'ES' => 'es', 'MX' => 'es', 'AR' => 'es', 'CO' => 'es', 'PE' => 'es',
        'CL' => 'es', 'EC' => 'es', 'VE' => 'es', 'GT' => 'es', 'CU' => 'es',
        'BO' => 'es', 'DO' => 'es', 'HN' => 'es', 'PY' => 'es', 'SV' => 'es',
        'NI' => 'es', 'CR' => 'es', 'PA' => 'es', 'UY' => 'es',
        // Lusophone
        'BR' => 'pt', 'PT' => 'pt', 'AO' => 'pt', 'MZ' => 'pt', 'CV' => 'pt',
        'GW' => 'pt', 'ST' => 'pt', 'TL' => 'pt',
        // Germanophone
        'DE' => 'de', 'AT' => 'de', 'LI' => 'de',
        // Russophone
        'RU' => 'ru', 'BY' => 'ru', 'KZ' => 'ru', 'KG' => 'ru', 'TJ' => 'ru',
        'UZ' => 'ru', 'TM' => 'ru', 'MD' => 'ru',
        // Arabophone
        'SA' => 'ar', 'AE' => 'ar', 'EG' => 'ar', 'IQ' => 'ar', 'JO' => 'ar',
        'LB' => 'ar', 'SY' => 'ar', 'YE' => 'ar', 'OM' => 'ar', 'KW' => 'ar',
        'BH' => 'ar', 'QA' => 'ar', 'LY' => 'ar', 'SD' => 'ar', 'SO' => 'ar',
        'MR' => 'ar', 'PS' => 'ar',
        // Hindi
        'IN' => 'hi',
        // Sinophone
        'CN' => 'zh', 'TW' => 'zh', 'HK' => 'zh', 'MO' => 'zh',
    ];

    /** Country code → continent code */
    public const COUNTRY_TO_CONTINENT = [
        // Afrique
        'DZ' => 'AF', 'AO' => 'AF', 'BJ' => 'AF', 'BW' => 'AF', 'BF' => 'AF', 'BI' => 'AF', 'CV' => 'AF',
        'CM' => 'AF', 'CF' => 'AF', 'TD' => 'AF', 'KM' => 'AF', 'CG' => 'AF', 'CD' => 'AF', 'CI' => 'AF',
        'DJ' => 'AF', 'EG' => 'AF', 'GQ' => 'AF', 'ER' => 'AF', 'SZ' => 'AF', 'ET' => 'AF', 'GA' => 'AF',
        'GM' => 'AF', 'GH' => 'AF', 'GN' => 'AF', 'GW' => 'AF', 'KE' => 'AF', 'LS' => 'AF', 'LR' => 'AF',
        'LY' => 'AF', 'MG' => 'AF', 'MW' => 'AF', 'ML' => 'AF', 'MR' => 'AF', 'MU' => 'AF', 'MA' => 'AF',
        'MZ' => 'AF', 'NA' => 'AF', 'NE' => 'AF', 'NG' => 'AF', 'RW' => 'AF', 'ST' => 'AF', 'SN' => 'AF',
        'SC' => 'AF', 'SL' => 'AF', 'SO' => 'AF', 'ZA' => 'AF', 'SS' => 'AF', 'SD' => 'AF', 'TZ' => 'AF',
        'TG' => 'AF', 'TN' => 'AF', 'UG' => 'AF', 'ZM' => 'AF', 'ZW' => 'AF',
        // Asie
        'AF' => 'AS', 'AM' => 'AS', 'AZ' => 'AS', 'BD' => 'AS', 'BT' => 'AS', 'BN' => 'AS', 'KH' => 'AS',
        'CN' => 'AS', 'GE' => 'AS', 'IN' => 'AS', 'ID' => 'AS', 'JP' => 'AS', 'KZ' => 'AS', 'KG' => 'AS',
        'LA' => 'AS', 'MY' => 'AS', 'MV' => 'AS', 'MN' => 'AS', 'MM' => 'AS', 'NP' => 'AS', 'KP' => 'AS',
        'KR' => 'AS', 'PK' => 'AS', 'PH' => 'AS', 'SG' => 'AS', 'LK' => 'AS', 'TW' => 'AS', 'TJ' => 'AS',
        'TH' => 'AS', 'TL' => 'AS', 'TM' => 'AS', 'UZ' => 'AS', 'VN' => 'AS', 'HK' => 'AS', 'MO' => 'AS',
        // Europe
        'AL' => 'EU', 'AD' => 'EU', 'AT' => 'EU', 'BY' => 'EU', 'BE' => 'EU', 'BA' => 'EU', 'BG' => 'EU',
        'HR' => 'EU', 'CY' => 'EU', 'CZ' => 'EU', 'DK' => 'EU', 'EE' => 'EU', 'FI' => 'EU', 'FR' => 'EU',
        'DE' => 'EU', 'GR' => 'EU', 'HU' => 'EU', 'IS' => 'EU', 'IE' => 'EU', 'IT' => 'EU', 'LV' => 'EU',
        'LI' => 'EU', 'LT' => 'EU', 'LU' => 'EU', 'MT' => 'EU', 'MD' => 'EU', 'MC' => 'EU', 'ME' => 'EU',
        'NL' => 'EU', 'MK' => 'EU', 'NO' => 'EU', 'PL' => 'EU', 'PT' => 'EU', 'RO' => 'EU', 'RU' => 'EU',
        'SM' => 'EU', 'RS' => 'EU', 'SK' => 'EU', 'SI' => 'EU', 'ES' => 'EU', 'SE' => 'EU', 'CH' => 'EU',
        'UA' => 'EU', 'GB' => 'EU', 'VA' => 'EU',
        // Amérique du Nord
        'AG' => 'NA', 'BS' => 'NA', 'BB' => 'NA', 'BZ' => 'NA', 'CA' => 'NA', 'CR' => 'NA', 'CU' => 'NA',
        'DM' => 'NA', 'DO' => 'NA', 'SV' => 'NA', 'GD' => 'NA', 'GT' => 'NA', 'HT' => 'NA', 'HN' => 'NA',
        'JM' => 'NA', 'MX' => 'NA', 'NI' => 'NA', 'PA' => 'NA', 'KN' => 'NA', 'LC' => 'NA', 'VC' => 'NA',
        'TT' => 'NA', 'US' => 'NA',
        // Amérique du Sud
        'AR' => 'SA', 'BO' => 'SA', 'BR' => 'SA', 'CL' => 'SA', 'CO' => 'SA', 'EC' => 'SA', 'GY' => 'SA',
        'PY' => 'SA', 'PE' => 'SA', 'SR' => 'SA', 'UY' => 'SA', 'VE' => 'SA',
        // Océanie
        'AU' => 'OC', 'FJ' => 'OC', 'KI' => 'OC', 'MH' => 'OC', 'FM' => 'OC', 'NR' => 'OC', 'NZ' => 'OC',
        'PW' => 'OC', 'PG' => 'OC', 'WS' => 'OC', 'SB' => 'OC', 'TO' => 'OC', 'TV' => 'OC', 'VU' => 'OC',
        // Moyen-Orient
        'BH' => 'ME', 'IR' => 'ME', 'IQ' => 'ME', 'IL' => 'ME', 'JO' => 'ME', 'KW' => 'ME', 'LB' => 'ME',
        'OM' => 'ME', 'PS' => 'ME', 'QA' => 'ME', 'SA' => 'ME', 'SY' => 'ME', 'TR' => 'ME', 'AE' => 'ME',
        'YE' => 'ME',
    ];

    /**
     * Get language from country code.
     */
    public static function languageForCountry(string $countryCode): ?string
    {
        return self::COUNTRY_TO_LANGUAGE[strtoupper($countryCode)] ?? null;
    }

    /**
     * Get continent from country code.
     */
    public static function continentForCountry(string $countryCode): ?string
    {
        return self::COUNTRY_TO_CONTINENT[strtoupper($countryCode)] ?? null;
    }

    /**
     * Normalize language code (ch→zh).
     */
    public static function normalizeLanguage(string $lang): string
    {
        return $lang === 'ch' ? 'zh' : $lang;
    }
}

<?php

declare(strict_types=1);

namespace App\Data;

class DefaultTemplates
{
    /**
     * All 12 event types.
     */
    public const EVENTS = [
        'new_registration',
        'call_completed',
        'payment_received',
        'daily_report',
        'new_provider',
        'new_contact_message',
        'new_press_message',
        'negative_review',
        'security_alert',
        'withdrawal_request',
        'captain_application',
        'user_feedback',
        'partner_application',
        'trustpilot_weekly_report',
    ];

    /**
     * All 9 supported languages.
     */
    public const LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];

    /**
     * Get all default templates (10 events x 9 languages).
     *
     * @return array<int, array{event_type: string, language: string, template: string}>
     */
    public static function all(): array
    {
        $templates = [];

        foreach (self::LANGUAGES as $language) {
            $eventTemplates = match ($language) {
                'fr' => self::frenchTemplates(),
                'en' => self::englishTemplates(),
                default => self::englishTemplates(), // Other languages default to EN
            };

            foreach ($eventTemplates as $eventType => $template) {
                $templates[] = [
                    'event_type' => $eventType,
                    'language' => $language,
                    'template' => $template,
                ];
            }
        }

        return $templates;
    }

    /**
     * Get templates for a specific language.
     *
     * @return array<string, string>
     */
    public static function forLanguage(string $language): array
    {
        return match ($language) {
            'fr' => self::frenchTemplates(),
            'en' => self::englishTemplates(),
            default => self::englishTemplates(),
        };
    }

    /**
     * French templates.
     *
     * Variables must match exactly what EventController passes.
     *
     * @return array<string, string>
     */
    private static function frenchTemplates(): array
    {
        return [
            'new_registration' => <<<'TPL'
                🆕 Nouvel inscrit

                👤 Role: {{ROLE_FR}}
                📧 Email: {{EMAIL}}
                📱 Tel: {{PHONE}}
                🌍 Pays: {{COUNTRY}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'call_completed' => <<<'TPL'
                📞 Appel termine

                👤 Client: {{CLIENT_NAME}}
                👨‍⚕️ Prestataire: {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})
                ⏱ Duree: {{DURATION_MINUTES}} min
                📅 {{DATE}} a {{TIME}}
                TPL,

            'payment_received' => <<<'TPL'
                💰 Paiement recu

                💵 Montant: {{TOTAL_AMOUNT}}$
                📊 Commission SOS: {{COMMISSION_AMOUNT}}$
                📅 {{DATE}} a {{TIME}}
                TPL,

            'daily_report' => <<<'TPL'
                📊 Rapport du {{DATE}}

                💰 CA: {{DAILY_CA}}$
                📈 Commission: {{DAILY_COMMISSION}}$
                👥 Inscriptions: {{REGISTRATION_COUNT}}
                📞 Appels: {{CALL_COUNT}}
                TPL,

            'new_provider' => <<<'TPL'
                👨‍⚕️ Nouveau prestataire

                👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})
                📧 {{EMAIL}}
                📱 {{PHONE}}
                🌍 {{COUNTRY}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'new_contact_message' => <<<'TPL'
                ✉️ Nouveau message de contact

                👤 De: {{SENDER_NAME}} ({{SENDER_EMAIL}})
                📋 Sujet: {{SUBJECT}}
                💬 {{MESSAGE_PREVIEW}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'new_press_message' => <<<'TPL'
                📰🎙 MESSAGE PRESSE / MEDIA

                👤 Journaliste: {{SENDER_NAME}}
                📧 Email: {{SENDER_EMAIL}}
                📋 Sujet: {{SUBJECT}}
                💬 {{MESSAGE_PREVIEW}}
                📅 {{DATE}} a {{TIME}}

                ⚡ Repondre sous 24h — priorite presse
                TPL,

            'negative_review' => <<<'TPL'
                ⚠️ Avis negatif

                👤 Client: {{CLIENT_NAME}}
                👨‍⚕️ Prestataire: {{PROVIDER_NAME}}
                ⭐ Note: {{RATING}}/5
                💬 {{COMMENT_PREVIEW}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'security_alert' => <<<'TPL'
                🚨 ALERTE SECURITE

                ⚠️ Type: {{ALERT_TYPE_FR}}
                👤 Email: {{USER_EMAIL}}
                🌐 IP: {{IP_ADDRESS}}
                🌍 Pays: {{COUNTRY}}
                📋 {{DETAILS}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'withdrawal_request' => <<<'TPL'
                💸 Demande de retrait

                👤 {{USER_NAME}} ({{USER_TYPE_FR}})
                💰 Montant: {{AMOUNT}}
                💳 Methode: {{PAYMENT_METHOD}}
                📋 {{PAYMENT_DETAILS}}
                🌍 {{COUNTRY}}
                🔗 {{ADMIN_URL}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'captain_application' => <<<'TPL'
                🎖 Candidature Captain

                👤 {{CANDIDATE_NAME}}
                📱 WhatsApp: {{WHATSAPP}}
                🌍 {{COUNTRY}}
                💬 {{MOTIVATION_PREVIEW}}
                📄 CV: {{HAS_CV}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'user_feedback' => <<<'TPL'
                💬 Nouveau feedback [{{FEEDBACK_TYPE}}]

                👤 De: {{USER_EMAIL}}
                📄 Page: {{PAGE}}
                📋 {{DESCRIPTION}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'partner_application' => <<<'TPL'
                🤝 Candidature partenaire

                👤 {{PARTNER_NAME}}
                📧 {{EMAIL}}
                🌐 Site: {{WEBSITE}}
                🌍 {{COUNTRY}}
                💬 {{MESSAGE_PREVIEW}}
                📅 {{DATE}} a {{TIME}}
                TPL,

            'trustpilot_weekly_report' => <<<'TPL'
                📊 Trustpilot — Rapport hebdo ({{PERIOD}})

                ⭐ Note globale : {{TRUST_SCORE}}/5 {{SCORE_DELTA}}
                📝 Total avis : {{TOTAL_REVIEWS}} {{NEW_REVIEWS_LABEL}}

                📈 Distribution :
                {{DISTRIBUTION}}

                {{NEW_REVIEWS_SECTION}}

                🔗 {{PROFILE_URL}}
                TPL,
        ];
    }

    /**
     * English templates.
     *
     * Uses the same variable names as French templates (EventController sends
     * language-agnostic names like ROLE_FR, PROVIDER_TYPE_FR, etc.).
     * The TemplateRenderer will simply substitute whatever the controller passes.
     *
     * @return array<string, string>
     */
    private static function englishTemplates(): array
    {
        return [
            'new_registration' => <<<'TPL'
                🆕 New Registration

                👤 Role: {{ROLE_FR}}
                📧 Email: {{EMAIL}}
                📱 Phone: {{PHONE}}
                🌍 Country: {{COUNTRY}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'call_completed' => <<<'TPL'
                📞 Call Completed

                👤 Client: {{CLIENT_NAME}}
                👨‍⚕️ Provider: {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})
                ⏱ Duration: {{DURATION_MINUTES}} min
                📅 {{DATE}} at {{TIME}}
                TPL,

            'payment_received' => <<<'TPL'
                💰 Payment Received

                💵 Amount: ${{TOTAL_AMOUNT}}
                📊 SOS Commission: ${{COMMISSION_AMOUNT}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'daily_report' => <<<'TPL'
                📊 Report for {{DATE}}

                💰 Revenue: ${{DAILY_CA}}
                📈 Commission: ${{DAILY_COMMISSION}}
                👥 Registrations: {{REGISTRATION_COUNT}}
                📞 Calls: {{CALL_COUNT}}
                TPL,

            'new_provider' => <<<'TPL'
                👨‍⚕️ New Provider

                👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})
                📧 {{EMAIL}}
                📱 {{PHONE}}
                🌍 {{COUNTRY}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'new_contact_message' => <<<'TPL'
                ✉️ New Contact Message

                👤 From: {{SENDER_NAME}} ({{SENDER_EMAIL}})
                📋 Subject: {{SUBJECT}}
                💬 {{MESSAGE_PREVIEW}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'new_press_message' => <<<'TPL'
                📰🎙 PRESS / MEDIA MESSAGE

                👤 Journalist: {{SENDER_NAME}}
                📧 Email: {{SENDER_EMAIL}}
                📋 Subject: {{SUBJECT}}
                💬 {{MESSAGE_PREVIEW}}
                📅 {{DATE}} at {{TIME}}

                ⚡ Reply within 24h — press priority
                TPL,

            'negative_review' => <<<'TPL'
                ⚠️ Negative Review

                👤 Client: {{CLIENT_NAME}}
                👨‍⚕️ Provider: {{PROVIDER_NAME}}
                ⭐ Rating: {{RATING}}/5
                💬 {{COMMENT_PREVIEW}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'security_alert' => <<<'TPL'
                🚨 SECURITY ALERT

                ⚠️ Type: {{ALERT_TYPE_FR}}
                👤 Email: {{USER_EMAIL}}
                🌐 IP: {{IP_ADDRESS}}
                🌍 Country: {{COUNTRY}}
                📋 {{DETAILS}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'withdrawal_request' => <<<'TPL'
                💸 Withdrawal Request

                👤 {{USER_NAME}} ({{USER_TYPE_FR}})
                💰 Amount: {{AMOUNT}}
                💳 Method: {{PAYMENT_METHOD}}
                📋 {{PAYMENT_DETAILS}}
                🌍 {{COUNTRY}}
                🔗 {{ADMIN_URL}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'captain_application' => <<<'TPL'
                🎖 Captain Application

                👤 {{CANDIDATE_NAME}}
                📱 WhatsApp: {{WHATSAPP}}
                🌍 {{COUNTRY}}
                💬 {{MOTIVATION_PREVIEW}}
                📄 CV: {{HAS_CV}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'user_feedback' => <<<'TPL'
                💬 New Feedback [{{FEEDBACK_TYPE}}]

                👤 From: {{USER_EMAIL}}
                📄 Page: {{PAGE}}
                📋 {{DESCRIPTION}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'partner_application' => <<<'TPL'
                🤝 Partner Application

                👤 {{PARTNER_NAME}}
                📧 {{EMAIL}}
                🌐 Website: {{WEBSITE}}
                🌍 {{COUNTRY}}
                💬 {{MESSAGE_PREVIEW}}
                📅 {{DATE}} at {{TIME}}
                TPL,

            'trustpilot_weekly_report' => <<<'TPL'
                📊 Trustpilot — Weekly Report ({{PERIOD}})

                ⭐ Overall rating: {{TRUST_SCORE}}/5 {{SCORE_DELTA}}
                📝 Total reviews: {{TOTAL_REVIEWS}} {{NEW_REVIEWS_LABEL}}

                📈 Distribution:
                {{DISTRIBUTION}}

                {{NEW_REVIEWS_SECTION}}

                🔗 {{PROFILE_URL}}
                TPL,
        ];
    }
}

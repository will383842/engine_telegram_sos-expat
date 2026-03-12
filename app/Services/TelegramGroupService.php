<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\GeoData;
use App\Models\TelegramGroup;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Group Service — find the right group for a user.
 *
 * Same resolution logic as WhatsApp groups (5 levels):
 *
 *   1. Continent + exact user language (chatters ONLY)
 *   2. User's selected language (language group)
 *   3. Language inferred from country (language group)
 *   4. Default group for the role (first enabled group)
 *   5. Any enabled group for the role (last resort)
 *
 * Messages are sent in the user's language (9 languages + EN fallback).
 */
class TelegramGroupService
{
    /** Roles that use continent-based group resolution. */
    private const CONTINENT_ROLES = ['chatter'];

    /** Translations for welcome and error messages (9 languages + EN fallback). */
    private const MESSAGES = [
        'fr' => [
            'welcome'       => "🎉 <b>Bienvenue {NAME} !</b>\n\nVotre compte Telegram a été lié avec succès à SOS-Expat.\nRôle : <b>{ROLE}</b>",
            'join_group'    => "📱 <b>Rejoignez votre groupe Telegram :</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nCe groupe vous permet d'échanger avec la communauté SOS-Expat !",
            'commands'      => "\nCommandes disponibles :\n/balance — Voir votre solde\n/stats — Voir vos statistiques\n/help — Aide",
            'expired'       => "⏰ Ce lien d'inscription a expiré.\n\nVeuillez générer un nouveau lien depuis votre dashboard SOS-Expat.",
            'invalid'       => "❌ Ce lien d'inscription n'est pas valide.\n\nVeuillez utiliser le lien depuis votre dashboard SOS-Expat.",
            'already_linked' => "✅ Votre compte Telegram est déjà lié à SOS-Expat !\n\nUtilisez /balance ou /stats pour voir vos informations.",
            'error'         => "❌ Une erreur est survenue. Veuillez réessayer depuis votre dashboard SOS-Expat.",
            'start'         => "👋 Bienvenue sur SOS-Expat !\n\nPour lier votre compte Telegram, utilisez le lien depuis votre dashboard SOS-Expat.\n📱 https://sos-expat.com",
            'unknown'       => "Pour lier votre compte, utilisez le lien depuis votre dashboard SOS-Expat.\n📱 https://sos-expat.com",
        ],
        'en' => [
            'welcome'       => "🎉 <b>Welcome {NAME}!</b>\n\nYour Telegram account has been successfully linked to SOS-Expat.\nRole: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>Join your Telegram group:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nThis group lets you connect with the SOS-Expat community!",
            'commands'      => "\nAvailable commands:\n/balance — Check your balance\n/stats — View your statistics\n/help — Help",
            'expired'       => "⏰ This registration link has expired.\n\nPlease generate a new link from your SOS-Expat dashboard.",
            'invalid'       => "❌ This registration link is not valid.\n\nPlease use the link from your SOS-Expat dashboard.",
            'already_linked' => "✅ Your Telegram account is already linked to SOS-Expat!\n\nUse /balance or /stats to see your information.",
            'error'         => "❌ An error occurred. Please try again from your SOS-Expat dashboard.",
            'start'         => "👋 Welcome to SOS-Expat!\n\nTo link your Telegram account, use the link from your SOS-Expat dashboard.\n📱 https://sos-expat.com",
            'unknown'       => "To link your account, use the link from your SOS-Expat dashboard.\n📱 https://sos-expat.com",
        ],
        'es' => [
            'welcome'       => "🎉 <b>¡Bienvenido/a {NAME}!</b>\n\nTu cuenta de Telegram se ha vinculado correctamente a SOS-Expat.\nRol: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>Únete a tu grupo de Telegram:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\n¡Este grupo te permite conectar con la comunidad SOS-Expat!",
            'commands'      => "\nComandos disponibles:\n/balance — Ver tu saldo\n/stats — Ver tus estadísticas\n/help — Ayuda",
            'expired'       => "⏰ Este enlace de registro ha expirado.\n\nPor favor, genera un nuevo enlace desde tu dashboard de SOS-Expat.",
            'invalid'       => "❌ Este enlace de registro no es válido.\n\nPor favor, usa el enlace desde tu dashboard de SOS-Expat.",
            'already_linked' => "✅ ¡Tu cuenta de Telegram ya está vinculada a SOS-Expat!\n\nUsa /balance o /stats para ver tu información.",
            'error'         => "❌ Ocurrió un error. Por favor, intenta de nuevo desde tu dashboard de SOS-Expat.",
            'start'         => "👋 ¡Bienvenido/a a SOS-Expat!\n\nPara vincular tu cuenta de Telegram, usa el enlace desde tu dashboard.\n📱 https://sos-expat.com",
            'unknown'       => "Para vincular tu cuenta, usa el enlace desde tu dashboard de SOS-Expat.\n📱 https://sos-expat.com",
        ],
        'pt' => [
            'welcome'       => "🎉 <b>Bem-vindo/a {NAME}!</b>\n\nSua conta do Telegram foi vinculada com sucesso ao SOS-Expat.\nFunção: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>Entre no seu grupo do Telegram:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nEste grupo permite que você se conecte com a comunidade SOS-Expat!",
            'commands'      => "\nComandos disponíveis:\n/balance — Ver seu saldo\n/stats — Ver suas estatísticas\n/help — Ajuda",
            'expired'       => "⏰ Este link de registro expirou.\n\nPor favor, gere um novo link no seu dashboard do SOS-Expat.",
            'invalid'       => "❌ Este link de registro não é válido.\n\nPor favor, use o link do seu dashboard do SOS-Expat.",
            'already_linked' => "✅ Sua conta do Telegram já está vinculada ao SOS-Expat!\n\nUse /balance ou /stats para ver suas informações.",
            'error'         => "❌ Ocorreu um erro. Por favor, tente novamente no seu dashboard do SOS-Expat.",
            'start'         => "👋 Bem-vindo/a ao SOS-Expat!\n\nPara vincular sua conta do Telegram, use o link no seu dashboard.\n📱 https://sos-expat.com",
            'unknown'       => "Para vincular sua conta, use o link no seu dashboard do SOS-Expat.\n📱 https://sos-expat.com",
        ],
        'de' => [
            'welcome'       => "🎉 <b>Willkommen {NAME}!</b>\n\nIhr Telegram-Konto wurde erfolgreich mit SOS-Expat verknüpft.\nRolle: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>Treten Sie Ihrer Telegram-Gruppe bei:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nDiese Gruppe ermöglicht den Austausch mit der SOS-Expat-Community!",
            'commands'      => "\nVerfügbare Befehle:\n/balance — Kontostand anzeigen\n/stats — Statistiken anzeigen\n/help — Hilfe",
            'expired'       => "⏰ Dieser Registrierungslink ist abgelaufen.\n\nBitte erstellen Sie einen neuen Link in Ihrem SOS-Expat-Dashboard.",
            'invalid'       => "❌ Dieser Registrierungslink ist ungültig.\n\nBitte verwenden Sie den Link aus Ihrem SOS-Expat-Dashboard.",
            'already_linked' => "✅ Ihr Telegram-Konto ist bereits mit SOS-Expat verknüpft!\n\nVerwenden Sie /balance oder /stats für Ihre Informationen.",
            'error'         => "❌ Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut über Ihr SOS-Expat-Dashboard.",
            'start'         => "👋 Willkommen bei SOS-Expat!\n\nUm Ihr Telegram-Konto zu verknüpfen, verwenden Sie den Link in Ihrem Dashboard.\n📱 https://sos-expat.com",
            'unknown'       => "Um Ihr Konto zu verknüpfen, verwenden Sie den Link in Ihrem SOS-Expat-Dashboard.\n📱 https://sos-expat.com",
        ],
        'ru' => [
            'welcome'       => "🎉 <b>Добро пожаловать, {NAME}!</b>\n\nВаш аккаунт Telegram успешно привязан к SOS-Expat.\nРоль: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>Присоединяйтесь к группе Telegram:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nЭта группа позволяет общаться с сообществом SOS-Expat!",
            'commands'      => "\nДоступные команды:\n/balance — Проверить баланс\n/stats — Посмотреть статистику\n/help — Помощь",
            'expired'       => "⏰ Эта ссылка для регистрации истекла.\n\nПожалуйста, создайте новую ссылку в вашем дашборде SOS-Expat.",
            'invalid'       => "❌ Эта ссылка для регистрации недействительна.\n\nПожалуйста, используйте ссылку из вашего дашборда SOS-Expat.",
            'already_linked' => "✅ Ваш аккаунт Telegram уже привязан к SOS-Expat!\n\nИспользуйте /balance или /stats для просмотра информации.",
            'error'         => "❌ Произошла ошибка. Пожалуйста, попробуйте снова через ваш дашборд SOS-Expat.",
            'start'         => "👋 Добро пожаловать в SOS-Expat!\n\nЧтобы привязать аккаунт Telegram, используйте ссылку из дашборда.\n📱 https://sos-expat.com",
            'unknown'       => "Чтобы привязать аккаунт, используйте ссылку из вашего дашборда SOS-Expat.\n📱 https://sos-expat.com",
        ],
        'ar' => [
            'welcome'       => "🎉 <b>مرحباً {NAME}!</b>\n\nتم ربط حساب Telegram الخاص بك بنجاح مع SOS-Expat.\nالدور: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>انضم إلى مجموعتك على Telegram:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nهذه المجموعة تتيح لك التواصل مع مجتمع SOS-Expat!",
            'commands'      => "\nالأوامر المتاحة:\n/balance — عرض رصيدك\n/stats — عرض إحصائياتك\n/help — المساعدة",
            'expired'       => "⏰ انتهت صلاحية رابط التسجيل هذا.\n\nيرجى إنشاء رابط جديد من لوحة التحكم الخاصة بك في SOS-Expat.",
            'invalid'       => "❌ رابط التسجيل هذا غير صالح.\n\nيرجى استخدام الرابط من لوحة التحكم الخاصة بك في SOS-Expat.",
            'already_linked' => "✅ حساب Telegram الخاص بك مرتبط بالفعل بـ SOS-Expat!\n\nاستخدم /balance أو /stats لعرض معلوماتك.",
            'error'         => "❌ حدث خطأ. يرجى المحاولة مرة أخرى من لوحة التحكم الخاصة بك في SOS-Expat.",
            'start'         => "👋 مرحباً بك في SOS-Expat!\n\nلربط حساب Telegram الخاص بك، استخدم الرابط من لوحة التحكم.\n📱 https://sos-expat.com",
            'unknown'       => "لربط حسابك، استخدم الرابط من لوحة التحكم الخاصة بك في SOS-Expat.\n📱 https://sos-expat.com",
        ],
        'zh' => [
            'welcome'       => "🎉 <b>欢迎 {NAME}！</b>\n\n您的 Telegram 账号已成功关联到 SOS-Expat。\n角色：<b>{ROLE}</b>",
            'join_group'    => "📱 <b>加入您的 Telegram 群组：</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\n此群组可让您与 SOS-Expat 社区交流！",
            'commands'      => "\n可用命令：\n/balance — 查看余额\n/stats — 查看统计数据\n/help — 帮助",
            'expired'       => "⏰ 此注册链接已过期。\n\n请从您的 SOS-Expat 控制面板生成新链接。",
            'invalid'       => "❌ 此注册链接无效。\n\n请使用您的 SOS-Expat 控制面板中的链接。",
            'already_linked' => "✅ 您的 Telegram 账号已关联到 SOS-Expat！\n\n使用 /balance 或 /stats 查看您的信息。",
            'error'         => "❌ 发生错误。请从您的 SOS-Expat 控制面板重试。",
            'start'         => "👋 欢迎来到 SOS-Expat！\n\n要关联您的 Telegram 账号，请使用控制面板中的链接。\n📱 https://sos-expat.com",
            'unknown'       => "要关联您的账号，请使用您的 SOS-Expat 控制面板中的链接。\n📱 https://sos-expat.com",
        ],
        'hi' => [
            'welcome'       => "🎉 <b>स्वागत है {NAME}!</b>\n\nआपका Telegram खाता सफलतापूर्वक SOS-Expat से जोड़ दिया गया है।\nभूमिका: <b>{ROLE}</b>",
            'join_group'    => "📱 <b>अपने Telegram समूह में शामिल हों:</b>\n<a href=\"{LINK}\">{GROUP}</a>\n\nयह समूह आपको SOS-Expat समुदाय से जुड़ने की अनुमति देता है!",
            'commands'      => "\nउपलब्ध कमांड:\n/balance — अपना बैलेंस देखें\n/stats — अपने आंकड़े देखें\n/help — सहायता",
            'expired'       => "⏰ यह पंजीकरण लिंक समाप्त हो गया है।\n\nकृपया अपने SOS-Expat डैशबोर्ड से एक नया लिंक बनाएं।",
            'invalid'       => "❌ यह पंजीकरण लिंक मान्य नहीं है।\n\nकृपया अपने SOS-Expat डैशबोर्ड से लिंक का उपयोग करें।",
            'already_linked' => "✅ आपका Telegram खाता पहले से SOS-Expat से जुड़ा हुआ है!\n\nअपनी जानकारी देखने के लिए /balance या /stats का उपयोग करें।",
            'error'         => "❌ एक त्रुटि हुई। कृपया अपने SOS-Expat डैशबोर्ड से पुनः प्रयास करें।",
            'start'         => "👋 SOS-Expat में आपका स्वागत है!\n\nअपना Telegram खाता जोड़ने के लिए, डैशबोर्ड से लिंक का उपयोग करें।\n📱 https://sos-expat.com",
            'unknown'       => "अपना खाता जोड़ने के लिए, अपने SOS-Expat डैशबोर्ड से लिंक का उपयोग करें।\n📱 https://sos-expat.com",
        ],
    ];

    /**
     * Get a translated message string with EN fallback.
     */
    public function t(string $lang, string $key, array $vars = []): string
    {
        $normalized = GeoData::normalizeLanguage($lang);
        $text = self::MESSAGES[$normalized][$key]
            ?? self::MESSAGES['en'][$key]
            ?? self::MESSAGES['fr'][$key]
            ?? '';

        foreach ($vars as $k => $v) {
            $text = str_replace("{{$k}}", (string) $v, $text);
        }

        return $text;
    }

    /**
     * Find the best Telegram group for a user based on role, language, and country.
     */
    public function findGroupForUser(string $role, string $language, string $country): ?TelegramGroup
    {
        $lang = GeoData::normalizeLanguage($language);
        $upperCountry = strtoupper($country);
        $continent = GeoData::continentForCountry($upperCountry);
        $countryLang = GeoData::languageForCountry($upperCountry);

        $groups = TelegramGroup::where('role', $role)
            ->where('enabled', true)
            ->where('link', '!=', '')
            ->get();

        if ($groups->isEmpty()) {
            return null;
        }

        // Level 1: Continent + exact user language (chatters ONLY)
        if (in_array($role, self::CONTINENT_ROLES, true) && $continent) {
            $match = $groups->first(fn (TelegramGroup $g) =>
                $g->type === 'continent' && $g->continent_code === $continent && $g->language === $lang
            );
            if ($match) return $match;
        }

        // Level 2: User's selected language
        $match = $groups->first(fn (TelegramGroup $g) =>
            $g->type === 'language' && $g->language === $lang
        );
        if ($match) return $match;

        // Level 3: Language inferred from country
        if ($countryLang && $countryLang !== $lang) {
            $match = $groups->first(fn (TelegramGroup $g) =>
                $g->type === 'language' && $g->language === $countryLang
            );
            if ($match) return $match;
        }

        // Level 4 & 5: Default / any enabled group
        return $groups->first();
    }

    /**
     * Generate the welcome message in the user's language.
     */
    public function buildWelcomeMessage(string $firstName, string $role, ?TelegramGroup $group, string $language = 'fr'): string
    {
        $roleLabel = GeoData::ROLE_LABELS[$role] ?? $role;

        $message = $this->t($language, 'welcome', ['NAME' => $firstName, 'ROLE' => $roleLabel]);

        if ($group && $group->hasLink()) {
            $message .= "\n\n" . $this->t($language, 'join_group', [
                'LINK' => $group->link,
                'GROUP' => $group->name,
            ]);
        }

        $message .= "\n" . $this->t($language, 'commands');

        return $message;
    }

    /**
     * Build error message in the user's language.
     */
    public function buildErrorMessage(string $errorType, string $language = 'fr'): string
    {
        $key = match ($errorType) {
            'expired' => 'expired',
            'invalid' => 'invalid',
            'already_linked' => 'already_linked',
            default => 'error',
        };

        return $this->t($language, $key);
    }

    /**
     * Build /start message (no code) in a given language.
     */
    public function buildStartMessage(string $language = 'fr'): string
    {
        return $this->t($language, 'start');
    }

    /**
     * Build generic message for unknown text.
     */
    public function buildUnknownMessage(string $language = 'fr'): string
    {
        return $this->t($language, 'unknown');
    }
}

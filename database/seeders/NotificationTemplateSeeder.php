<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\AdminConfig;
use App\Models\TelegramBot;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ==================== FRENCH (12) ====================
            ['new_registration', 'fr', "🆕 *Nouvelle inscription*\n\n👤 Rôle: {{ROLE_FR}}\n📧 Email: {{EMAIL}}\n📱 Téléphone: {{PHONE}}\n🌍 Pays: {{COUNTRY}}\n📅 {{DATE}} à {{TIME}}"],
            ['call_completed', 'fr', "📞 *Appel terminé*\n\n👤 Client: {{CLIENT_NAME}}\n🎯 Prestataire: {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ Durée: {{DURATION_MINUTES}} min\n📅 {{DATE}} à {{TIME}}"],
            ['payment_received', 'fr', "💰 *Paiement reçu*\n\n💵 CA Total: {{TOTAL_AMOUNT}}€\n🏢 Commission SOS Expat: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} à {{TIME}}"],
            ['daily_report', 'fr', "📊 *Rapport quotidien — {{DATE}}*\n\n💰 *Chiffre d'affaires*\n   CA Total: {{DAILY_CA}}€\n   Commission: {{DAILY_COMMISSION}}€\n\n📈 *Activité*\n   📝 Inscriptions: {{REGISTRATION_COUNT}}\n   📞 Appels: {{CALL_COUNT}}\n\n🕐 Généré à {{TIME}} (Paris)"],
            ['new_provider', 'fr', "👨‍⚖️ *Nouveau prestataire*\n\n👤 {{PROVIDER_NAME}}\n🎯 Type: {{PROVIDER_TYPE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n\n⏳ En attente de validation\n📅 {{DATE}} à {{TIME}}"],
            ['new_contact_message', 'fr', "📩 *Nouveau message contact*\n\n👤 De: {{SENDER_NAME}}\n📧 {{SENDER_EMAIL}}\n📝 Sujet: {{SUBJECT}}\n\n💬 {{MESSAGE_PREVIEW}}\n\n📅 {{DATE}} à {{TIME}}"],
            ['negative_review', 'fr', "⚠️ *Avis négatif reçu*\n\n⭐ Note: {{RATING}}/5\n👤 Client: {{CLIENT_NAME}}\n🎯 Prestataire: {{PROVIDER_NAME}}\n\n💬 {{COMMENT_PREVIEW}}\n\n📅 {{DATE}} à {{TIME}}"],
            ['security_alert', 'fr', "🔐 *Alerte sécurité*\n\n🚨 Type: {{ALERT_TYPE_FR}}\n👤 Compte: {{USER_EMAIL}}\n🌍 Localisation: {{COUNTRY}}\n🔗 IP: {{IP_ADDRESS}}\n\n📋 {{DETAILS}}\n\n📅 {{DATE}} à {{TIME}}"],
            ['withdrawal_request', 'fr', "💳 *Demande de retrait*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 Montant: {{AMOUNT}} \$\n🏦 Via: {{PAYMENT_METHOD}}\n📋 Détails: {{PAYMENT_DETAILS}}\n🌍 Pays: {{COUNTRY}}\n\n📅 {{DATE}} à {{TIME}}\n\n🔗 [Console admin]({{ADMIN_URL}})"],
            ['captain_application', 'fr', "👑 *Candidature Captain Chatter*\n\n👤 {{CANDIDATE_NAME}}\n📱 WhatsApp: {{WHATSAPP}}\n🌍 Pays: {{COUNTRY}}\n📎 CV: {{HAS_CV}}\n\n💬 {{MOTIVATION_PREVIEW}}\n\n📅 {{DATE}} à {{TIME}}"],
            ['user_feedback', 'fr', "💬 *Nouveau feedback [{{FEEDBACK_TYPE}}]*\n\n👤 De: {{USER_EMAIL}}\n📄 Page: {{PAGE}}\n\n📋 {{DESCRIPTION}}\n\n📅 {{DATE}} à {{TIME}}"],
            ['partner_application', 'fr', "🤝 *Candidature partenaire*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 Site: {{WEBSITE}}\n🌍 Pays: {{COUNTRY}}\n\n💬 {{MESSAGE_PREVIEW}}\n\n📅 {{DATE}} à {{TIME}}"],

            // ==================== ENGLISH (12) ====================
            ['new_registration', 'en', "🆕 *New Registration*\n\n👤 Role: {{ROLE_FR}}\n📧 Email: {{EMAIL}}\n📱 Phone: {{PHONE}}\n🌍 Country: {{COUNTRY}}\n📅 {{DATE}} at {{TIME}}"],
            ['call_completed', 'en', "📞 *Call Completed*\n\n👤 Client: {{CLIENT_NAME}}\n🎯 Provider: {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ Duration: {{DURATION_MINUTES}} min\n📅 {{DATE}} at {{TIME}}"],
            ['payment_received', 'en', "💰 *Payment Received*\n\n💵 Total Revenue: {{TOTAL_AMOUNT}}€\n🏢 SOS Expat Commission: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} at {{TIME}}"],
            ['daily_report', 'en', "📊 *Daily Report — {{DATE}}*\n\n💰 *Revenue*\n   Total: {{DAILY_CA}}€\n   Commission: {{DAILY_COMMISSION}}€\n\n📈 *Activity*\n   📝 Registrations: {{REGISTRATION_COUNT}}\n   📞 Calls: {{CALL_COUNT}}\n\n🕐 Generated at {{TIME}} (Paris)"],
            ['new_provider', 'en', "👨‍⚖️ *New Provider*\n\n👤 {{PROVIDER_NAME}}\n🎯 Type: {{PROVIDER_TYPE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n\n⏳ Awaiting validation\n📅 {{DATE}} at {{TIME}}"],
            ['new_contact_message', 'en', "📩 *New Contact Message*\n\n👤 From: {{SENDER_NAME}}\n📧 {{SENDER_EMAIL}}\n📝 Subject: {{SUBJECT}}\n\n💬 {{MESSAGE_PREVIEW}}\n\n📅 {{DATE}} at {{TIME}}"],
            ['negative_review', 'en', "⚠️ *Negative Review*\n\n⭐ Rating: {{RATING}}/5\n👤 Client: {{CLIENT_NAME}}\n🎯 Provider: {{PROVIDER_NAME}}\n\n💬 {{COMMENT_PREVIEW}}\n\n📅 {{DATE}} at {{TIME}}"],
            ['security_alert', 'en', "🔐 *Security Alert*\n\n🚨 Type: {{ALERT_TYPE_FR}}\n👤 Account: {{USER_EMAIL}}\n🌍 Location: {{COUNTRY}}\n🔗 IP: {{IP_ADDRESS}}\n\n📋 {{DETAILS}}\n\n📅 {{DATE}} at {{TIME}}"],
            ['withdrawal_request', 'en', "💳 *Withdrawal Request*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 Amount: {{AMOUNT}} \$\n🏦 Via: {{PAYMENT_METHOD}}\n📋 Details: {{PAYMENT_DETAILS}}\n🌍 Country: {{COUNTRY}}\n\n📅 {{DATE}} at {{TIME}}\n\n🔗 [Admin console]({{ADMIN_URL}})"],
            ['captain_application', 'en', "👑 *Captain Chatter Application*\n\n👤 {{CANDIDATE_NAME}}\n📱 WhatsApp: {{WHATSAPP}}\n🌍 Country: {{COUNTRY}}\n📎 CV: {{HAS_CV}}\n\n💬 {{MOTIVATION_PREVIEW}}\n\n📅 {{DATE}} at {{TIME}}"],
            ['user_feedback', 'en', "💬 *New Feedback [{{FEEDBACK_TYPE}}]*\n\n👤 From: {{USER_EMAIL}}\n📄 Page: {{PAGE}}\n\n📋 {{DESCRIPTION}}\n\n📅 {{DATE}} at {{TIME}}"],
            ['partner_application', 'en', "🤝 *Partner Application*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 Website: {{WEBSITE}}\n🌍 Country: {{COUNTRY}}\n\n💬 {{MESSAGE_PREVIEW}}\n\n📅 {{DATE}} at {{TIME}}"],

            // ==================== SPANISH (12) ====================
            ['new_registration', 'es', "🆕 *Nueva inscripción*\n\n👤 Rol: {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} a las {{TIME}}"],
            ['call_completed', 'es', "📞 *Llamada completada*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} min\n📅 {{DATE}} a las {{TIME}}"],
            ['payment_received', 'es', "💰 *Pago recibido*\n\n💵 {{TOTAL_AMOUNT}}€ | Comisión: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} a las {{TIME}}"],
            ['daily_report', 'es', "📊 *Informe diario — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (París)"],
            ['new_provider', 'es', "👨‍⚖️ *Nuevo proveedor*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} a las {{TIME}}"],
            ['new_contact_message', 'es', "📩 *Nuevo mensaje*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} a las {{TIME}}"],
            ['negative_review', 'es', "⚠️ *Reseña negativa*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} a las {{TIME}}"],
            ['security_alert', 'es', "🔐 *Alerta de seguridad*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} a las {{TIME}}"],
            ['withdrawal_request', 'es', "💳 *Solicitud de retiro*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} a las {{TIME}}"],
            ['captain_application', 'es', "👑 *Candidatura Captain*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} a las {{TIME}}"],
            ['user_feedback', 'es', "💬 *Nuevo feedback [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} a las {{TIME}}"],
            ['partner_application', 'es', "🤝 *Candidatura socio*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} a las {{TIME}}"],

            // ==================== GERMAN (12) ====================
            ['new_registration', 'de', "🆕 *Neue Anmeldung*\n\n👤 Rolle: {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} um {{TIME}}"],
            ['call_completed', 'de', "📞 *Anruf beendet*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} Min.\n📅 {{DATE}} um {{TIME}}"],
            ['payment_received', 'de', "💰 *Zahlung erhalten*\n\n💵 {{TOTAL_AMOUNT}}€ | Provision: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} um {{TIME}}"],
            ['daily_report', 'de', "📊 *Tagesbericht — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (Paris)"],
            ['new_provider', 'de', "👨‍⚖️ *Neuer Anbieter*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} um {{TIME}}"],
            ['new_contact_message', 'de', "📩 *Neue Nachricht*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} um {{TIME}}"],
            ['negative_review', 'de', "⚠️ *Negative Bewertung*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} um {{TIME}}"],
            ['security_alert', 'de', "🔐 *Sicherheitswarnung*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} um {{TIME}}"],
            ['withdrawal_request', 'de', "💳 *Auszahlungsantrag*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} um {{TIME}}"],
            ['captain_application', 'de', "👑 *Captain Bewerbung*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} um {{TIME}}"],
            ['user_feedback', 'de', "💬 *Neues Feedback [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} um {{TIME}}"],
            ['partner_application', 'de', "🤝 *Partnerbewerbung*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} um {{TIME}}"],

            // ==================== PORTUGUESE (12) ====================
            ['new_registration', 'pt', "🆕 *Nova inscrição*\n\n👤 {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} às {{TIME}}"],
            ['call_completed', 'pt', "📞 *Chamada concluída*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} min\n📅 {{DATE}} às {{TIME}}"],
            ['payment_received', 'pt', "💰 *Pagamento recebido*\n\n💵 {{TOTAL_AMOUNT}}€ | Comissão: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} às {{TIME}}"],
            ['daily_report', 'pt', "📊 *Relatório — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (Paris)"],
            ['new_provider', 'pt', "👨‍⚖️ *Novo prestador*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} às {{TIME}}"],
            ['new_contact_message', 'pt', "📩 *Nova mensagem*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} às {{TIME}}"],
            ['negative_review', 'pt', "⚠️ *Avaliação negativa*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} às {{TIME}}"],
            ['security_alert', 'pt', "🔐 *Alerta de segurança*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} às {{TIME}}"],
            ['withdrawal_request', 'pt', "💳 *Pedido de saque*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} às {{TIME}}"],
            ['captain_application', 'pt', "👑 *Candidatura Captain*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} às {{TIME}}"],
            ['user_feedback', 'pt', "💬 *Novo feedback [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} às {{TIME}}"],
            ['partner_application', 'pt', "🤝 *Candidatura parceiro*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} às {{TIME}}"],

            // ==================== RUSSIAN (12) ====================
            ['new_registration', 'ru', "🆕 *Новая регистрация*\n\n👤 {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} в {{TIME}}"],
            ['call_completed', 'ru', "📞 *Звонок завершён*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} мин\n📅 {{DATE}} в {{TIME}}"],
            ['payment_received', 'ru', "💰 *Платёж получен*\n\n💵 {{TOTAL_AMOUNT}}€ | Комиссия: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} в {{TIME}}"],
            ['daily_report', 'ru', "📊 *Отчёт — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (Париж)"],
            ['new_provider', 'ru', "👨‍⚖️ *Новый провайдер*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} в {{TIME}}"],
            ['new_contact_message', 'ru', "📩 *Новое сообщение*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} в {{TIME}}"],
            ['negative_review', 'ru', "⚠️ *Негативный отзыв*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} в {{TIME}}"],
            ['security_alert', 'ru', "🔐 *Безопасность*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} в {{TIME}}"],
            ['withdrawal_request', 'ru', "💳 *Запрос на вывод*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} в {{TIME}}"],
            ['captain_application', 'ru', "👑 *Заявка Captain*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} в {{TIME}}"],
            ['user_feedback', 'ru', "💬 *Новый отзыв [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} в {{TIME}}"],
            ['partner_application', 'ru', "🤝 *Заявка партнёра*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} в {{TIME}}"],

            // ==================== CHINESE (12) ====================
            ['new_registration', 'zh', "🆕 *新用户注册*\n\n👤 {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} {{TIME}}"],
            ['call_completed', 'zh', "📞 *通话结束*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} 分钟\n📅 {{DATE}} {{TIME}}"],
            ['payment_received', 'zh', "💰 *收到付款*\n\n💵 {{TOTAL_AMOUNT}}€ | 佣金: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} {{TIME}}"],
            ['daily_report', 'zh', "📊 *日报 — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (巴黎)"],
            ['new_provider', 'zh', "👨‍⚖️ *新服务商*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} {{TIME}}"],
            ['new_contact_message', 'zh', "📩 *新消息*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['negative_review', 'zh', "⚠️ *差评*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['security_alert', 'zh', "🔐 *安全警报*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} {{TIME}}"],
            ['withdrawal_request', 'zh', "💳 *提现请求*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} {{TIME}}"],
            ['captain_application', 'zh', "👑 *Captain申请*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['user_feedback', 'zh', "💬 *新反馈 [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} {{TIME}}"],
            ['partner_application', 'zh', "🤝 *合作伙伴申请*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],

            // ==================== HINDI (12) ====================
            ['new_registration', 'hi', "🆕 *नया पंजीकरण*\n\n👤 {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} {{TIME}}"],
            ['call_completed', 'hi', "📞 *कॉल पूरी*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} मिनट\n📅 {{DATE}} {{TIME}}"],
            ['payment_received', 'hi', "💰 *भुगतान प्राप्त*\n\n💵 {{TOTAL_AMOUNT}}€ | कमीशन: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} {{TIME}}"],
            ['daily_report', 'hi', "📊 *दैनिक रिपोर्ट — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (पेरिस)"],
            ['new_provider', 'hi', "👨‍⚖️ *नया प्रदाता*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} {{TIME}}"],
            ['new_contact_message', 'hi', "📩 *नया संदेश*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['negative_review', 'hi', "⚠️ *नकारात्मक समीक्षा*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['security_alert', 'hi', "🔐 *सुरक्षा चेतावनी*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} {{TIME}}"],
            ['withdrawal_request', 'hi', "💳 *निकासी अनुरोध*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} {{TIME}}"],
            ['captain_application', 'hi', "👑 *Captain आवेदन*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['user_feedback', 'hi', "💬 *नया फीडबैक [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} {{TIME}}"],
            ['partner_application', 'hi', "🤝 *साझेदार आवेदन*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],

            // ==================== ARABIC (12) ====================
            ['new_registration', 'ar', "🆕 *تسجيل جديد*\n\n👤 {{ROLE_FR}}\n📧 {{EMAIL}}\n📱 {{PHONE}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} {{TIME}}"],
            ['call_completed', 'ar', "📞 *مكالمة مكتملة*\n\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n⏱️ {{DURATION_MINUTES}} دقيقة\n📅 {{DATE}} {{TIME}}"],
            ['payment_received', 'ar', "💰 *تم استلام الدفعة*\n\n💵 {{TOTAL_AMOUNT}}€ | عمولة: {{COMMISSION_AMOUNT}}€\n📅 {{DATE}} {{TIME}}"],
            ['daily_report', 'ar', "📊 *التقرير اليومي — {{DATE}}*\n\n💰 {{DAILY_CA}}€ | 📝 {{REGISTRATION_COUNT}} | 📞 {{CALL_COUNT}}\n🕐 {{TIME}} (باريس)"],
            ['new_provider', 'ar', "👨‍⚖️ *مزود جديد*\n\n👤 {{PROVIDER_NAME}} ({{PROVIDER_TYPE_FR}})\n📧 {{EMAIL}}\n🌍 {{COUNTRY}}\n📅 {{DATE}} {{TIME}}"],
            ['new_contact_message', 'ar', "📩 *رسالة جديدة*\n\n👤 {{SENDER_NAME}} ({{SENDER_EMAIL}})\n📝 {{SUBJECT}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['negative_review', 'ar', "⚠️ *تقييم سلبي*\n\n⭐ {{RATING}}/5\n👤 {{CLIENT_NAME}} → {{PROVIDER_NAME}}\n💬 {{COMMENT_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['security_alert', 'ar', "🔐 *تنبيه أمني*\n\n🚨 {{ALERT_TYPE_FR}}\n👤 {{USER_EMAIL}}\n🌍 {{COUNTRY}} | IP: {{IP_ADDRESS}}\n📋 {{DETAILS}}\n📅 {{DATE}} {{TIME}}"],
            ['withdrawal_request', 'ar', "💳 *طلب سحب*\n\n👤 {{USER_NAME}} ({{USER_TYPE_FR}})\n💰 {{AMOUNT}} \$ via {{PAYMENT_METHOD}}\n📅 {{DATE}} {{TIME}}"],
            ['captain_application', 'ar', "👑 *طلب Captain*\n\n👤 {{CANDIDATE_NAME}}\n🌍 {{COUNTRY}} | CV: {{HAS_CV}}\n💬 {{MOTIVATION_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
            ['user_feedback', 'ar', "💬 *تعليق جديد [{{FEEDBACK_TYPE}}]*\n\n👤 {{USER_EMAIL}}\n📄 {{PAGE}}\n📋 {{DESCRIPTION}}\n📅 {{DATE}} {{TIME}}"],
            ['partner_application', 'ar', "🤝 *طلب شراكة*\n\n👤 {{PARTNER_NAME}}\n📧 {{EMAIL}}\n🌐 {{WEBSITE}}\n🌍 {{COUNTRY}}\n💬 {{MESSAGE_PREVIEW}}\n📅 {{DATE}} {{TIME}}"],
        ];

        foreach ($templates as [$eventType, $language, $template]) {
            NotificationTemplate::updateOrCreate(
                ['event_type' => $eventType, 'language' => $language],
                ['template' => $template, 'is_custom' => false]
            );
        }

        $this->command->info('Seeded ' . count($templates) . ' notification templates (12 events x 9 languages)');

        // Main bot events (business/operations — NOT inbox messages)
        $mainBotEvents = [
            'new_registration' => true,
            'call_completed' => true,
            'payment_received' => true,
            'daily_report' => true,
            'new_provider' => true,
            'negative_review' => true,
            'security_alert' => true,
            // Inbox events DISABLED on main bot:
            'new_contact_message' => false,
            'user_feedback' => false,
            'captain_application' => false,
            'partner_application' => false,
            'withdrawal_request' => false,
        ];

        // Inbox bot events (messages, feedbacks, candidatures — NOT withdrawals)
        $inboxEvents = [
            'new_contact_message' => true,
            'user_feedback' => true,
            'captain_application' => true,
            'partner_application' => true,
            'withdrawal_request' => false,
        ];

        // Withdrawals bot events (ONLY withdrawal requests)
        $withdrawalEvents = [
            'withdrawal_request' => true,
        ];

        // Seed AdminConfig (legacy, backward compat)
        AdminConfig::updateOrCreate(
            ['id' => 1],
            [
                'recipient_chat_id' => '',
                'notifications' => $mainBotEvents,
                'updated_by' => 'seeder',
            ]
        );

        $this->command->info('Seeded AdminConfig with main bot events');

        // Seed Telegram Bots
        TelegramBot::updateOrCreate(
            ['slug' => 'main'],
            [
                'name' => 'SOS Expat Bot',
                'token' => env('TELEGRAM_BOT_TOKEN', ''),
                'recipient_chat_id' => '',
                'notifications' => $mainBotEvents,
                'is_active' => true,
            ]
        );

        TelegramBot::updateOrCreate(
            ['slug' => 'inbox'],
            [
                'name' => 'SOS Expat Inbox Bot',
                'token' => env('TELEGRAM_INBOX_BOT_TOKEN', ''),
                'recipient_chat_id' => '',
                'notifications' => $inboxEvents,
                'is_active' => true,
            ]
        );

        TelegramBot::updateOrCreate(
            ['slug' => 'withdrawals'],
            [
                'name' => 'SOS Expat Withdrawals Bot',
                'token' => env('TELEGRAM_WITHDRAWALS_BOT_TOKEN', ''),
                'recipient_chat_id' => '',
                'notifications' => $withdrawalEvents,
                'is_active' => true,
            ]
        );

        $this->command->info('Seeded 3 Telegram bots (main + inbox + withdrawals)');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use App\Services\TelegramBotService;
use App\Services\TelegramGroupService;
use App\Services\WithdrawalConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class BotController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $botService,
        private readonly OnboardingService $onboardingService,
        private readonly TelegramGroupService $telegramGroupService,
        private readonly WithdrawalConfirmationService $withdrawalService,
    ) {}

    /**
     * Handle incoming Telegram webhook updates.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $update = $request->all();

        try {
            // Handle callback queries (inline keyboard buttons)
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }

            // Handle regular messages
            if (isset($update['message'])) {
                return $this->handleMessage($update['message']);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Bot webhook error', [
                'error'  => $e->getMessage(),
                'update' => $update,
            ]);

            // Always return 200 to Telegram to avoid re-delivery
            return response()->json(['ok' => true]);
        }
    }

    /**
     * Handle incoming webhook from the onboarding bot.
     */
    public function handleOnboardingWebhook(Request $request): JsonResponse
    {
        $update = $request->all();

        try {
            if (isset($update['message'])) {
                return $this->handleOnboardingMessage($update['message']);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Onboarding bot webhook error', [
                'error'  => $e->getMessage(),
                'update' => $update,
            ]);

            return response()->json(['ok' => true]);
        }
    }

    /* ------------------------------------------------------------------
     * Onboarding bot message handling
     * ---------------------------------------------------------------- */

    private function handleOnboardingMessage(array $message): JsonResponse
    {
        $text   = $message['text'] ?? '';
        $chatId = (string) ($message['chat']['id'] ?? '');
        $from   = $message['from'] ?? [];

        if ($chatId === '') {
            return response()->json(['ok' => true]);
        }

        $onboardingBot = $this->getOnboardingBotInstance();
        if (!$onboardingBot) {
            Log::error('Onboarding bot token not configured');
            return response()->json(['ok' => true]);
        }

        // /start CODE — onboarding deep link
        if (str_starts_with($text, '/start ')) {
            $code = trim(substr($text, 7));

            if ($code !== '') {
                $result = $this->onboardingService->handleBotStart(
                    telegramId: (int) ($from['id'] ?? 0),
                    telegramUsername: $from['username'] ?? '',
                    firstName: $from['first_name'] ?? '',
                    lastName: $from['last_name'] ?? '',
                    code: $code,
                );

                if ($result['success'] && $result['link']) {
                    // Send welcome message with group link
                    $welcomeMessage = $this->telegramGroupService->buildWelcomeMessage(
                        $from['first_name'] ?? 'Utilisateur',
                        $result['link']->role,
                        $result['group'],
                    );
                    $this->botService->sendMessageWithBot($onboardingBot, $chatId, $welcomeMessage);
                } else {
                    // Send error message
                    $errorMessage = $this->telegramGroupService->buildErrorMessage(
                        $result['errorType'] ?? 'invalid'
                    );
                    $this->botService->sendMessageWithBot($onboardingBot, $chatId, $errorMessage);
                }

                return response()->json(['ok' => true]);
            }
        }

        // Plain /start without code
        if ($text === '/start') {
            $this->botService->sendMessageWithBot(
                $onboardingBot,
                $chatId,
                "👋 Bienvenue sur SOS-Expat !\n\n"
                . "Pour lier votre compte Telegram, utilisez le lien depuis votre dashboard SOS-Expat.\n\n"
                . "📱 https://sos-expat.com",
            );
            return response()->json(['ok' => true]);
        }

        // Any other message
        $this->botService->sendMessageWithBot(
            $onboardingBot,
            $chatId,
            "Pour lier votre compte, utilisez le lien depuis votre dashboard SOS-Expat.\n📱 https://sos-expat.com",
        );

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------
     * Admin bot message handling
     * ---------------------------------------------------------------- */

    private function handleMessage(array $message): JsonResponse
    {
        $text   = $message['text'] ?? '';
        $chatId = (string) ($message['chat']['id'] ?? '');
        $from   = $message['from'] ?? [];

        if ($chatId === '') {
            return response()->json(['ok' => true]);
        }

        // /start CODE — onboarding deep link (backward compat if someone uses main bot)
        if (str_starts_with($text, '/start ')) {
            $code = trim(substr($text, 7));

            if ($code !== '') {
                $result = $this->onboardingService->handleBotStart(
                    telegramId: (int) ($from['id'] ?? 0),
                    telegramUsername: $from['username'] ?? '',
                    firstName: $from['first_name'] ?? '',
                    lastName: $from['last_name'] ?? '',
                    code: $code,
                );

                if ($result['success'] && $result['link']) {
                    $welcomeMessage = $this->telegramGroupService->buildWelcomeMessage(
                        $from['first_name'] ?? 'Utilisateur',
                        $result['link']->role,
                        $result['group'],
                    );
                    $this->botService->sendMessage($chatId, $welcomeMessage);
                } else {
                    $errorMessage = $this->telegramGroupService->buildErrorMessage(
                        $result['errorType'] ?? 'invalid'
                    );
                    $this->botService->sendMessage($chatId, $errorMessage);
                }

                return response()->json(['ok' => true]);
            }
        }

        // Plain /start without code
        if ($text === '/start') {
            $this->botService->sendMessage(
                $chatId,
                "Bienvenue sur le bot SOS-Expat !\n\nCommandes disponibles :\n/balance — Voir votre solde\n/stats — Voir vos statistiques\n/help — Aide",
            );

            return response()->json(['ok' => true]);
        }

        // /balance — show user balance
        if ($text === '/balance') {
            return $this->handleBalance($chatId);
        }

        // /stats — show user stats
        if ($text === '/stats') {
            return $this->handleStats($chatId);
        }

        // /help — show help message
        if ($text === '/help') {
            $this->botService->sendMessage(
                $chatId,
                "Commandes disponibles :\n\n"
                . "/balance — Afficher votre solde actuel\n"
                . "/stats — Afficher vos statistiques\n"
                . "/help — Afficher ce message d'aide\n\n"
                . "Pour toute question, contactez le support sur https://sos-expat.com",
            );

            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------
     * Callback query handling (inline keyboards)
     * ---------------------------------------------------------------- */

    private function handleCallbackQuery(array $callbackQuery): JsonResponse
    {
        $data   = $callbackQuery['data'] ?? '';
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');

        if ($chatId === '' || $data === '') {
            return response()->json(['ok' => true]);
        }

        // Withdrawal confirmation: "withdraw_confirm:{code}"
        if (str_starts_with($data, 'withdraw_confirm:')) {
            $code   = substr($data, 18);
            $result = $this->withdrawalService->confirm($code);

            $text = $result
                ? "Retrait confirme ! Le traitement va commencer."
                : "Impossible de confirmer ce retrait. Il a peut-etre deja ete traite ou a expire.";

            $this->botService->answerCallbackQuery(
                $callbackQuery['id'],
                $result ? 'Confirme !' : 'Erreur',
            );
            $this->botService->sendMessage($chatId, $text);

            return response()->json(['ok' => true]);
        }

        // Withdrawal cancellation: "withdraw_cancel:{withdrawalId}"
        if (str_starts_with($data, 'withdraw_cancel:')) {
            $code   = substr($data, 17);
            $result = $this->withdrawalService->cancel($code);

            $text = $result
                ? "Retrait annule. Votre solde a ete restaure."
                : "Impossible d'annuler ce retrait. Il a peut-etre deja ete traite ou a expire.";

            $this->botService->answerCallbackQuery(
                $callbackQuery['id'],
                $result ? 'Annule !' : 'Erreur',
            );
            $this->botService->sendMessage($chatId, $text);

            return response()->json(['ok' => true]);
        }

        // Unknown callback — acknowledge silently
        $this->botService->answerCallbackQuery($callbackQuery['id']);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------
     * Command implementations
     * ---------------------------------------------------------------- */

    private function handleBalance(string $chatId): JsonResponse
    {
        try {
            $balance = $this->botService->getUserBalanceFromFirestore($chatId);

            if ($balance === null) {
                $this->botService->sendMessage(
                    $chatId,
                    "Aucun compte lie a ce chat Telegram.\nUtilisez le lien d'onboarding depuis votre dashboard pour lier votre compte.",
                );
            } else {
                $formatted = number_format($balance / 100, 2);
                $this->botService->sendMessage(
                    $chatId,
                    "Votre solde actuel : \${$formatted}",
                );
            }
        } catch (\Throwable $e) {
            Log::error('Balance command error', ['chatId' => $chatId, 'error' => $e->getMessage()]);
            $this->botService->sendMessage($chatId, "Erreur lors de la recuperation de votre solde. Reessayez plus tard.");
        }

        return response()->json(['ok' => true]);
    }

    private function handleStats(string $chatId): JsonResponse
    {
        try {
            $stats = $this->botService->getUserStatsFromFirestore($chatId);

            if ($stats === null) {
                $this->botService->sendMessage(
                    $chatId,
                    "Aucun compte lie a ce chat Telegram.\nUtilisez le lien d'onboarding depuis votre dashboard pour lier votre compte.",
                );
            } else {
                $message = "Vos statistiques :\n\n"
                    . "Commissions totales : \$" . number_format(($stats['totalCommissions'] ?? 0) / 100, 2) . "\n"
                    . "Parrainages : " . ($stats['referralCount'] ?? 0) . "\n"
                    . "Clics liens : " . ($stats['clickCount'] ?? 0) . "\n"
                    . "Niveau : " . ($stats['level'] ?? 'N/A');

                $this->botService->sendMessage($chatId, $message);
            }
        } catch (\Throwable $e) {
            Log::error('Stats command error', ['chatId' => $chatId, 'error' => $e->getMessage()]);
            $this->botService->sendMessage($chatId, "Erreur lors de la recuperation de vos statistiques. Reessayez plus tard.");
        }

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */

    private function getOnboardingBotInstance(): ?Nutgram
    {
        $token = config('telegram.onboarding_bot_token');
        if (!$token) {
            return null;
        }

        return TelegramBotService::forToken($token);
    }
}

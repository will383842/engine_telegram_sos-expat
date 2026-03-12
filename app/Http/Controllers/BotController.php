<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FirebaseWithdrawalCallbackService;
use App\Services\OnboardingService;
use App\Services\TelegramBotService;
use App\Services\TelegramGroupService;
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
        private readonly FirebaseWithdrawalCallbackService $firebaseWithdrawalService,
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
     * Handles both messages (onboarding /start) and callback_query (withdrawal confirmations).
     */
    public function handleOnboardingWebhook(Request $request): JsonResponse
    {
        $update = $request->all();

        try {
            // Handle callback queries (withdrawal confirmation buttons: wc_confirm_ / wc_cancel_)
            if (isset($update['callback_query'])) {
                return $this->handleOnboardingCallbackQuery($update['callback_query']);
            }

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
     * Onboarding bot callback query handling (withdrawal confirmations)
     * ---------------------------------------------------------------- */

    private function handleOnboardingCallbackQuery(array $callbackQuery): JsonResponse
    {
        $data = $callbackQuery['data'] ?? '';

        // Handle Firebase withdrawal confirmation callbacks: wc_confirm_{code} / wc_cancel_{code}
        if (str_starts_with($data, 'wc_confirm_') || str_starts_with($data, 'wc_cancel_')) {
            $onboardingBot = $this->getOnboardingBotInstance();
            if (!$onboardingBot) {
                Log::error('Onboarding bot token not configured for withdrawal callback');
                return response()->json(['ok' => true]);
            }

            $this->firebaseWithdrawalService->handleCallback($callbackQuery, $onboardingBot);
            return response()->json(['ok' => true]);
        }

        // Unknown callback — acknowledge silently
        $onboardingBot = $this->getOnboardingBotInstance();
        if ($onboardingBot) {
            $this->botService->answerCallbackQueryWithBot($onboardingBot, $callbackQuery['id'] ?? '');
        }

        return response()->json(['ok' => true]);
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
                // Detect Telegram client language for fallback
                $telegramLang = $from['language_code'] ?? 'en';

                $result = $this->onboardingService->handleBotStart(
                    telegramId: (int) ($from['id'] ?? 0),
                    telegramUsername: $from['username'] ?? '',
                    firstName: $from['first_name'] ?? '',
                    lastName: $from['last_name'] ?? '',
                    code: $code,
                );

                // Use Firestore language on success, Telegram client language on error
                $lang = $result['success']
                    ? ($result['language'] ?? $telegramLang)
                    : $telegramLang;

                if ($result['success'] && $result['link']) {
                    $welcomeMessage = $this->telegramGroupService->buildWelcomeMessage(
                        $from['first_name'] ?? 'User',
                        $result['link']->role,
                        $result['group'],
                        $lang,
                    );
                    $this->botService->sendMessageWithBot($onboardingBot, $chatId, $welcomeMessage);
                } else {
                    $errorMessage = $this->telegramGroupService->buildErrorMessage(
                        $result['errorType'] ?? 'invalid',
                        $lang,
                    );
                    $this->botService->sendMessageWithBot($onboardingBot, $chatId, $errorMessage);
                }

                return response()->json(['ok' => true]);
            }
        }

        // Plain /start without code — detect language from Telegram client
        if ($text === '/start') {
            $lang = $from['language_code'] ?? 'en';
            $this->botService->sendMessageWithBot(
                $onboardingBot,
                $chatId,
                $this->telegramGroupService->buildStartMessage($lang),
            );
            return response()->json(['ok' => true]);
        }

        // Any other message
        $lang = $from['language_code'] ?? 'en';
        $this->botService->sendMessageWithBot(
            $onboardingBot,
            $chatId,
            $this->telegramGroupService->buildUnknownMessage($lang),
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

        // Detect Telegram client language for all messages
        $telegramLang = $from['language_code'] ?? 'en';

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

                // Use Firestore language on success, Telegram client language on error
                $lang = $result['success']
                    ? ($result['language'] ?? $telegramLang)
                    : $telegramLang;

                if ($result['success'] && $result['link']) {
                    $welcomeMessage = $this->telegramGroupService->buildWelcomeMessage(
                        $from['first_name'] ?? 'User',
                        $result['link']->role,
                        $result['group'],
                        $lang,
                    );
                    $this->botService->sendMessage($chatId, $welcomeMessage);
                } else {
                    $errorMessage = $this->telegramGroupService->buildErrorMessage(
                        $result['errorType'] ?? 'invalid',
                        $lang,
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
                $this->telegramGroupService->buildStartMessage($telegramLang),
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
                $this->telegramGroupService->t($telegramLang, 'commands')
                    . "\n\nhttps://sos-expat.com",
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

        // Firebase withdrawal confirmation callbacks: wc_confirm_{code} / wc_cancel_{code}
        if (str_starts_with($data, 'wc_confirm_') || str_starts_with($data, 'wc_cancel_')) {
            $this->firebaseWithdrawalService->handleCallback($callbackQuery, $this->botService->getBot());
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

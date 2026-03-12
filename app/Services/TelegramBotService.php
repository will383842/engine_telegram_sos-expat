<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelegramBot;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use App\Models\RateLimit;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected Nutgram $bot;
    protected FirestoreService $firestoreService;

    /** @var array<string, Nutgram> Cache of bot instances by token */
    protected static array $botInstances = [];

    public function __construct(FirestoreService $firestoreService)
    {
        $this->bot = new Nutgram(config('telegram.bot_token'));
        $this->firestoreService = $firestoreService;
    }

    /**
     * Get a Nutgram instance for a specific bot token.
     * Uses caching to avoid creating multiple instances for the same token.
     */
    public static function forToken(string $token): Nutgram
    {
        if (!isset(self::$botInstances[$token])) {
            self::$botInstances[$token] = new Nutgram($token);
        }

        return self::$botInstances[$token];
    }

    /**
     * Get a Nutgram instance for a bot by its slug.
     * Falls back to the main bot if slug not found.
     */
    public static function forSlug(string $slug): ?Nutgram
    {
        $telegramBot = TelegramBot::bySlug($slug);

        if (!$telegramBot) {
            Log::warning('TelegramBotService: Bot not found for slug', ['slug' => $slug]);
            return null;
        }

        return self::forToken($telegramBot->token);
    }

    /**
     * Send a text message to a chat.
     */
    public function sendMessage(
        string $chatId,
        string $text,
        string $parseMode = 'HTML',
        ?array $replyMarkup = null,
    ): bool {
        return $this->sendMessageWithBot($this->bot, $chatId, $text, $parseMode, $replyMarkup);
    }

    /**
     * Send a text message using a specific Nutgram instance.
     */
    public function sendMessageWithBot(
        Nutgram $bot,
        string $chatId,
        string $text,
        string $parseMode = 'HTML',
        ?array $replyMarkup = null,
    ): bool {
        try {
            $text = mb_substr($text, 0, (int) config('telegram.max_message_length', 4096));

            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ];

            if ($replyMarkup) {
                $params['reply_markup'] = $replyMarkup;
            }

            $bot->sendMessage(...$params);

            $this->trackSent();

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            $this->trackFailed();

            return false;
        }
    }

    /**
     * Validate bot token by calling getMe.
     *
     * @return array{id: int, first_name: string, username: string, is_bot: bool}
     */
    public function validateBot(): array
    {
        try {
            $me = $this->bot->getMe();

            return [
                'id' => $me->id,
                'first_name' => $me->first_name ?? '',
                'username' => $me->username ?? '',
                'is_bot' => $me->is_bot ?? true,
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram validateBot failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate a specific bot token.
     *
     * @return array{id: int, first_name: string, username: string, is_bot: bool}
     */
    public function validateBotToken(string $token): array
    {
        try {
            $bot = self::forToken($token);
            $me = $bot->getMe();

            return [
                'id' => $me->id,
                'first_name' => $me->first_name ?? '',
                'username' => $me->username ?? '',
                'is_bot' => $me->is_bot ?? true,
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram validateBotToken failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get recent updates (useful for admin chat ID retrieval).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset = 0): array
    {
        try {
            $updates = $this->bot->getUpdates(
                offset: $offset > 0 ? $offset : null,
                limit: 100,
            );

            $result = [];
            foreach ($updates as $update) {
                $message = $update->message;
                if ($message) {
                    $result[] = [
                        'update_id' => $update->update_id,
                        'chat_id' => (string) $message->chat->id,
                        'username' => $message->from?->username ?? '',
                        'first_name' => $message->from?->first_name ?? '',
                        'text' => $message->text ?? '',
                        'date' => $message->date,
                    ];
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Telegram getUpdates failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get information about a chat.
     *
     * @return array{id: string, type: string, title: ?string, username: ?string, first_name: ?string}|null
     */
    public function getChatInfo(string $chatId): ?array
    {
        try {
            $chat = $this->bot->getChat($chatId);

            return [
                'id' => (string) $chat->id,
                'type' => $chat->type instanceof \BackedEnum ? $chat->type->value : (string) $chat->type,
                'title' => $chat->title ?? null,
                'username' => $chat->username ?? null,
                'first_name' => $chat->first_name ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram getChatInfo failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send a message with an inline keyboard (e.g. for withdrawal confirmation).
     *
     * @param array<int, array{text: string, callback_data: string}> $buttons
     */
    public function sendInlineKeyboard(string $chatId, string $text, array $buttons): bool
    {
        try {
            $text = mb_substr($text, 0, (int) config('telegram.max_message_length', 4096));

            $keyboardButtons = [];
            foreach ($buttons as $button) {
                $keyboardButtons[] = InlineKeyboardButton::make(
                    text: $button['text'],
                    callback_data: $button['callback_data'],
                );
            }

            $markup = InlineKeyboardMarkup::make()->addRow(...$keyboardButtons);

            $this->bot->sendMessage(
                chat_id: $chatId,
                text: $text,
                parse_mode: 'HTML',
                reply_markup: $markup,
            );

            $this->trackSent();

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram sendInlineKeyboard failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            $this->trackFailed();

            return false;
        }
    }

    /**
     * Increment daily sent counter.
     */
    protected function trackSent(): void
    {
        try {
            $rateLimit = RateLimit::firstOrCreate(
                ['date' => now()->toDateString()],
                ['messages_sent' => 0, 'messages_failed' => 0, 'peak_per_second' => 0],
            );
            $rateLimit->increment('messages_sent');
        } catch (\Throwable $e) {
            Log::warning('Failed to track rate limit', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Increment daily failed counter.
     */
    protected function trackFailed(): void
    {
        try {
            $rateLimit = RateLimit::firstOrCreate(
                ['date' => now()->toDateString()],
                ['messages_sent' => 0, 'messages_failed' => 0, 'peak_per_second' => 0],
            );
            $rateLimit->increment('messages_failed');
        } catch (\Throwable $e) {
            Log::warning('Failed to track rate limit failure', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Answer a callback query (acknowledge inline keyboard button press).
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        try {
            $this->bot->answerCallbackQuery(
                callback_query_id: $callbackQueryId,
                text: $text,
            );
        } catch (\Throwable $e) {
            Log::error('Telegram answerCallbackQuery failed', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Answer a callback query using a specific bot instance.
     */
    public function answerCallbackQueryWithBot(
        Nutgram $bot,
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false,
    ): void {
        try {
            $bot->answerCallbackQuery(
                callback_query_id: $callbackQueryId,
                text: $text,
                show_alert: $showAlert,
            );
        } catch (\Throwable $e) {
            Log::error('Telegram answerCallbackQueryWithBot failed', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Edit a message's text using a specific bot instance.
     */
    public function editMessageTextWithBot(
        Nutgram $bot,
        string $chatId,
        int $messageId,
        string $text,
        string $parseMode = 'HTML',
    ): bool {
        try {
            $bot->editMessageText(
                text: $text,
                chat_id: $chatId,
                message_id: $messageId,
                parse_mode: $parseMode,
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram editMessageTextWithBot failed', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get user balance info from Firestore chatters collection by telegram_id.
     *
     * @return array{balance: int, tirelire: int, total_withdrawn: int}|null
     */
    public function getUserBalanceFromFirestore(string $chatId): ?array
    {
        try {
            $results = $this->firestoreService->queryCollection('chatters', [
                ['field' => 'telegram_id', 'operator' => '==', 'value' => $chatId],
            ], 1);

            if (empty($results)) {
                return null;
            }

            $chatter = $results[0];

            return [
                'balance' => $chatter['balance'] ?? 0,
                'tirelire' => $chatter['tirelire'] ?? 0,
                'total_withdrawn' => $chatter['total_withdrawn'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram getUserBalanceFromFirestore failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get user stats from Firestore chatters collection by telegram_id.
     *
     * @return array{total_commissions: int, total_calls: int, total_referrals: int, status: string}|null
     */
    public function getUserStatsFromFirestore(string $chatId): ?array
    {
        try {
            $results = $this->firestoreService->queryCollection('chatters', [
                ['field' => 'telegram_id', 'operator' => '==', 'value' => $chatId],
            ], 1);

            if (empty($results)) {
                return null;
            }

            $chatter = $results[0];

            return [
                'total_commissions' => $chatter['total_commissions'] ?? 0,
                'total_calls' => $chatter['total_calls'] ?? 0,
                'total_referrals' => $chatter['total_referrals'] ?? 0,
                'status' => $chatter['status'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram getUserStatsFromFirestore failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the underlying Nutgram instance.
     */
    public function getBot(): Nutgram
    {
        return $this->bot;
    }
}

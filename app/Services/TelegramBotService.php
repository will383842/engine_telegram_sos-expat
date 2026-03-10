<?php

declare(strict_types=1);

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use App\Models\RateLimit;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected Nutgram $bot;
    protected FirestoreService $firestoreService;

    public function __construct(FirestoreService $firestoreService)
    {
        $this->bot = new Nutgram(config('telegram.bot_token'));
        $this->firestoreService = $firestoreService;
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

            $this->bot->sendMessage(...$params);

            // Track daily rate
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

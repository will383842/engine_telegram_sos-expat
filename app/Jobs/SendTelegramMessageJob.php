<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MessageQueue;
use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 10, 20];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    public function __construct(
        public readonly string $chatId,
        public readonly string $message,
        public readonly string $parseMode = 'HTML',
        public readonly ?array $replyMarkup = null,
        public readonly ?int $messageQueueId = null,
        public readonly string $botSlug = 'main',
    ) {
        $this->onQueue('telegram-messages');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Resolve the correct bot instance based on slug
        $bot = $this->resolveBotInstance();

        $parseModeEnum = match (strtoupper($this->parseMode)) {
            'MARKDOWN', 'MARKDOWNV2' => ParseMode::MARKDOWN_V2,
            default => ParseMode::HTML,
        };

        $bot->sendMessage(
            text: $this->message,
            chat_id: $this->chatId,
            parse_mode: $parseModeEnum,
            reply_markup: $this->replyMarkup,
        );

        // Update MessageQueue record if linked
        if ($this->messageQueueId) {
            $record = MessageQueue::find($this->messageQueueId);
            $record?->markAsSent();
        }

        Log::info('SendTelegramMessageJob: message sent', [
            'chat_id' => $this->chatId,
            'bot_slug' => $this->botSlug,
            'message_queue_id' => $this->messageQueueId,
        ]);
    }

    /**
     * Resolve the Nutgram bot instance for the given slug.
     */
    protected function resolveBotInstance(): Nutgram
    {
        // Try to find bot in telegram_bots table
        $telegramBot = TelegramBot::bySlug($this->botSlug);

        if ($telegramBot) {
            return TelegramBotService::forToken($telegramBot->token);
        }

        // Fallback to main bot from config
        Log::warning('SendTelegramMessageJob: Bot slug not found, using main config', ['bot_slug' => $this->botSlug]);
        return new Nutgram(config('telegram.bot_token'));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendTelegramMessageJob: failed', [
            'chat_id' => $this->chatId,
            'bot_slug' => $this->botSlug,
            'message_queue_id' => $this->messageQueueId,
            'error' => $exception->getMessage(),
            'attempt' => $this->attempts(),
        ]);

        if ($this->messageQueueId) {
            $record = MessageQueue::find($this->messageQueueId);
            $record?->markAsDead($exception->getMessage());
        }
    }
}

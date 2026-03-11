<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendTelegramMessageJob;
use App\Models\MessageQueue;
use Illuminate\Console\Command;

class ProcessQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:process-queue {--batch=25 : Batch size}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending messages in the Telegram queue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $this->info("Processing message queue (batch size: {$batchSize})...");

        $messages = MessageQueue::retryable()
            ->orderBy('created_at', 'asc')
            ->limit($batchSize)
            ->get();

        if ($messages->isEmpty()) {
            $this->info('No pending messages in queue.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($messages as $message) {
            /** @var MessageQueue $message */
            if (! $message->markAsClaimed()) {
                // Already claimed by another process
                continue;
            }

            SendTelegramMessageJob::dispatch(
                chatId: $message->chat_id,
                message: $message->message,
                parseMode: $message->parse_mode ?? 'HTML',
                messageQueueId: $message->id,
                botSlug: $message->bot_slug ?? 'main',
            );

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} message(s) to the telegram-messages queue.");

        return self::SUCCESS;
    }
}

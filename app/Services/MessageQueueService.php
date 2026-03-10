<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MessageQueue;
use Illuminate\Support\Facades\Log;

class MessageQueueService
{

    /**
     * Enqueue a message for delivery.
     *
     * Generates an idempotency key to prevent duplicate sends within a 1-minute window.
     */
    public function enqueue(
        string $chatId,
        string $message,
        string $source = 'notification',
        ?string $parseMode = 'HTML',
    ): void {
        // Idempotency key: SHA-256 of chatId + message + current minute
        $minuteKey = now()->format('Y-m-d H:i');
        $idempotencyKey = hash('sha256', $chatId . $message . $minuteKey);

        // Check for duplicate within the current minute
        $exists = MessageQueue::where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['pending', 'processing', 'sent'])
            ->exists();

        if ($exists) {
            Log::info('MessageQueueService: Duplicate message skipped', [
                'chat_id' => $chatId,
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
            ]);
            return;
        }

        MessageQueue::create([
            'chat_id' => $chatId,
            'message' => $message,
            'parse_mode' => $parseMode ?? 'HTML',
            'status' => 'pending',
            'attempts' => 0,
            'max_retries' => config('telegram.queue.max_retries', 3),
            'idempotency_key' => $idempotencyKey,
            'source' => $source,
        ]);
    }

    /**
     * Reprocess dead-letter messages (retry once more).
     *
     * @return int Number of messages requeued.
     */
    public function reprocessDeadLetters(): int
    {
        $deadMessages = MessageQueue::where('status', 'dead')->get();

        $count = 0;
        foreach ($deadMessages as $msg) {
            $msg->update([
                'status' => 'pending',
                'attempts' => 0,
                'error' => null,
                'next_retry_at' => null,
                'claimed_at' => null,
            ]);
            $count++;
        }

        Log::info('MessageQueueService: Reprocessed dead letters', ['count' => $count]);

        return $count;
    }

    /**
     * Get queue statistics.
     *
     * @return array{pending: int, processing: int, sent: int, failed: int, dead: int}
     */
    public function getStats(): array
    {
        return [
            'pending' => MessageQueue::where('status', 'pending')->count(),
            'processing' => MessageQueue::where('status', 'processing')->count(),
            'sent' => MessageQueue::where('status', 'sent')->count(),
            'failed' => MessageQueue::where('status', 'failed')->count(),
            'dead' => MessageQueue::where('status', 'dead')->count(),
        ];
    }
}

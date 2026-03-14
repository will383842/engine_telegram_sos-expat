<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelegramBot;
use App\Services\MessageQueueService;
use App\Services\TrustpilotScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrustpilotWeeklyReportCommand extends Command
{
    protected $signature = 'trustpilot:weekly-report';

    protected $description = 'Scrape Trustpilot and send a weekly report via Telegram';

    public function handle(
        TrustpilotScraperService $scraper,
        MessageQueueService $messageQueue,
    ): int {
        $this->info('Scraping Trustpilot...');

        try {
            // 1. Scrape the profile page
            $data = $scraper->scrape();

            $this->info("Trust Score: {$data['trust_score']} | Total reviews: {$data['total_reviews']}");

            // 2. Create a snapshot (compares with previous)
            $snapshot = $scraper->createSnapshot($data);

            $this->info("New reviews this week: {$snapshot->new_reviews_count}");

            // 3. Format the Telegram message
            $message = $scraper->formatTelegramReport($snapshot);

            $this->line('');
            $this->line($message);

            // 4. Send via Telegram
            $botSlug = config('trustpilot.bot_slug', 'main');
            $bot = TelegramBot::bySlug($botSlug);

            if (!$bot?->recipient_chat_id) {
                $this->warn("No recipient chat_id for bot '{$botSlug}', report not sent");
                return self::FAILURE;
            }

            $messageQueue->enqueue(
                chatId: $bot->recipient_chat_id,
                message: $message,
                source: 'trustpilot_weekly_report',
                parseMode: 'HTML',
                botSlug: $botSlug,
            );

            $this->info('Report queued for Telegram delivery.');

            Log::info('trustpilot:weekly-report completed', [
                'trust_score' => $snapshot->trust_score,
                'total_reviews' => $snapshot->total_reviews,
                'new_reviews_count' => $snapshot->new_reviews_count,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");

            Log::error('trustpilot:weekly-report failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send error notification to admin
            $this->sendErrorAlert($messageQueue, $e);

            return self::FAILURE;
        }
    }

    /**
     * Send an error alert to admin if scraping fails.
     */
    protected function sendErrorAlert(MessageQueueService $messageQueue, \Throwable $e): void
    {
        try {
            $botSlug = config('trustpilot.bot_slug', 'main');
            $bot = TelegramBot::bySlug($botSlug);

            if (!$bot?->recipient_chat_id) {
                return;
            }

            $message = "⚠️ <b>Trustpilot — Erreur scraping</b>\n\n"
                . "Le rapport hebdomadaire n'a pas pu être généré.\n"
                . "Erreur : <code>" . htmlspecialchars(mb_substr($e->getMessage(), 0, 200), ENT_QUOTES) . "</code>\n"
                . "📅 " . now()->format('d/m/Y H:i');

            $messageQueue->enqueue(
                chatId: $bot->recipient_chat_id,
                message: $message,
                source: 'trustpilot_weekly_report_error',
                parseMode: 'HTML',
                botSlug: $botSlug,
            );
        } catch (\Throwable) {
            // Don't throw from error handler
        }
    }
}

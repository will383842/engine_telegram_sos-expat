<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

class ProcessCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The queue this job should be dispatched to.
     */
    public string $queue = 'telegram-campaigns';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $batchSize = 25,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(Nutgram $bot): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning('ProcessCampaignBatchJob: campaign not found', [
                'campaign_id' => $this->campaignId,
            ]);

            return;
        }

        // Skip if campaign is no longer processing
        if ($campaign->status !== 'processing') {
            Log::info('ProcessCampaignBatchJob: campaign not in processing state', [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
            ]);

            return;
        }

        $parseModeEnum = match (strtoupper($campaign->parse_mode ?? 'HTML')) {
            'MARKDOWN', 'MARKDOWNV2' => ParseMode::MARKDOWN_V2,
            default => ParseMode::HTML,
        };

        // Get next batch of pending recipients
        $recipients = $campaign->recipients()
            ->pending()
            ->limit($this->batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            // No more recipients — mark campaign as completed
            $campaign->markAsCompleted();

            Log::info('ProcessCampaignBatchJob: campaign completed', [
                'campaign_id' => $campaign->id,
                'sent' => $campaign->sent_count,
                'failed' => $campaign->failed_count,
            ]);

            return;
        }

        $sentCount = 0;
        $failedCount = 0;
        $delayMs = config('telegram.rate_limit.send_delay_ms', 50);

        foreach ($recipients as $recipient) {
            /** @var CampaignRecipient $recipient */
            try {
                $bot->sendMessage(
                    text: $campaign->message,
                    chat_id: $recipient->chat_id,
                    parse_mode: $parseModeEnum,
                );

                $recipient->markAsSent();
                $sentCount++;
            } catch (Throwable $e) {
                $recipient->markAsFailed($e->getMessage());
                $failedCount++;

                Log::warning('ProcessCampaignBatchJob: failed to send to recipient', [
                    'campaign_id' => $campaign->id,
                    'chat_id' => $recipient->chat_id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Rate limiting delay between messages
            usleep($delayMs * 1000);
        }

        // Update campaign counters
        $campaign->increment('sent_count', $sentCount);
        $campaign->increment('failed_count', $failedCount);
        $campaign->refresh();

        Log::info('ProcessCampaignBatchJob: batch processed', [
            'campaign_id' => $campaign->id,
            'batch_sent' => $sentCount,
            'batch_failed' => $failedCount,
            'total_sent' => $campaign->sent_count,
            'total_failed' => $campaign->failed_count,
        ]);

        // Check if more recipients remain
        $remainingCount = $campaign->recipients()->pending()->count();

        if ($remainingCount > 0) {
            // Dispatch another batch job
            self::dispatch($this->campaignId, $this->batchSize);
        } else {
            $campaign->markAsCompleted();

            Log::info('ProcessCampaignBatchJob: campaign completed', [
                'campaign_id' => $campaign->id,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ProcessCampaignBatchJob: job failed', [
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
        ]);

        // Re-dispatch if there are remaining pending recipients
        $campaign = Campaign::find($this->campaignId);
        if ($campaign && $campaign->recipients()->pending()->count() > 0) {
            self::dispatch($this->campaignId, $this->batchSize)->delay(now()->addSeconds(10));
        }
    }
}

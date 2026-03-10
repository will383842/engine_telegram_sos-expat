<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(
        protected TelegramBotService $telegramBot,
    ) {}

    /**
     * Create a new broadcast campaign.
     *
     * @param array{name: string, message: string, parse_mode?: string, created_by?: string, filters?: array, scheduled_at?: string, recipients: array<int, string>} $data
     */
    public function create(array $data): Campaign
    {
        $campaign = Campaign::create([
            'name' => $data['name'],
            'message' => $data['message'],
            'parse_mode' => $data['parse_mode'] ?? 'HTML',
            'status' => 'pending',
            'total_recipients' => count($data['recipients'] ?? []),
            'sent_count' => 0,
            'failed_count' => 0,
            'created_by' => $data['created_by'] ?? null,
            'filters' => $data['filters'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        // Create recipient records
        $recipients = $data['recipients'] ?? [];
        foreach ($recipients as $chatId) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'chat_id' => (string) $chatId,
                'status' => 'pending',
            ]);
        }

        return $campaign;
    }

    /**
     * Cancel a campaign (only if pending or processing).
     */
    public function cancel(int $id): bool
    {
        $campaign = Campaign::find($id);

        if (!$campaign) {
            return false;
        }

        if (!in_array($campaign->status, ['pending', 'processing'], true)) {
            Log::warning('CampaignService: Cannot cancel campaign in status', [
                'id' => $id,
                'status' => $campaign->status,
            ]);
            return false;
        }

        $campaign->update(['status' => 'cancelled']);

        // Cancel pending recipients
        CampaignRecipient::where('campaign_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'failed', 'error' => 'Campaign cancelled']);

        return true;
    }

    /**
     * Get all campaigns with pagination and optional filters.
     *
     * @param array{status?: string, per_page?: int} $filters
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Campaign::orderByDesc('created_at');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Get campaign detail with recipients stats.
     */
    public function getDetail(int $id): Campaign
    {
        $campaign = Campaign::findOrFail($id);

        // Load recipient stats
        $campaign->setAttribute('recipients_stats', [
            'pending' => $campaign->recipients()->where('status', 'pending')->count(),
            'sent' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed' => $campaign->recipients()->where('status', 'failed')->count(),
        ]);

        return $campaign;
    }

    /**
     * Process a batch of campaign recipients.
     */
    public function processBatch(Campaign $campaign, int $batchSize = 25): void
    {
        if ($campaign->status === 'cancelled') {
            return;
        }

        // Mark as processing if still pending
        if ($campaign->status === 'pending') {
            $campaign->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);
        }

        $delayMs = (int) config('telegram.rate_limit.send_delay_ms', 50);

        // Get pending recipients
        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->limit($batchSize)
            ->get();

        if ($recipients->isEmpty()) {
            // All done — mark campaign as completed
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            return;
        }

        foreach ($recipients as $recipient) {
            // Check if campaign was cancelled mid-batch
            $campaign->refresh();
            if ($campaign->status === 'cancelled') {
                return;
            }

            $sent = $this->telegramBot->sendMessage(
                chatId: $recipient->chat_id,
                text: $campaign->message,
                parseMode: $campaign->parse_mode,
            );

            if ($sent) {
                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                $campaign->increment('sent_count');
            } else {
                $recipient->update([
                    'status' => 'failed',
                    'error' => 'Send failed',
                ]);
                $campaign->increment('failed_count');
            }

            // Respect rate limiting
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        // Check if all recipients processed
        $pendingCount = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        if ($pendingCount === 0) {
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}

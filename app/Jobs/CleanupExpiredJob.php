<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\MessageQueue;
use App\Models\NotificationLog;
use App\Models\OnboardingLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $counts = [
            'onboarding_links' => $this->cleanExpiredOnboardingLinks(),
            'message_queue' => $this->cleanOldMessageQueueRecords(),
            'notification_logs' => $this->cleanOldNotificationLogs(),
            'stale_processing' => $this->resetStaleProcessingMessages(),
            'old_campaigns' => $this->cleanOldCampaigns(),
        ];

        Log::info('CleanupExpiredJob: cleanup completed', $counts);

        return $counts;
    }

    /**
     * Clean expired onboarding links (> 24h, status pending -> expired).
     */
    private function cleanExpiredOnboardingLinks(): int
    {
        $expiryHours = config('telegram.onboarding.link_expiry_hours', 24);

        return OnboardingLink::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Clean old message queue records (> 7 days, status sent/dead).
     */
    private function cleanOldMessageQueueRecords(): int
    {
        $cleanupDays = config('telegram.queue.cleanup_days', 7);

        return MessageQueue::whereIn('status', ['sent', 'dead'])
            ->where('updated_at', '<', now()->subDays($cleanupDays))
            ->delete();
    }

    /**
     * Clean old notification logs (> 30 days).
     */
    private function cleanOldNotificationLogs(): int
    {
        return NotificationLog::where('created_at', '<', now()->subDays(30))
            ->delete();
    }

    /**
     * Reset stale 'processing' messages stuck for more than 5 minutes.
     */
    private function resetStaleProcessingMessages(): int
    {
        return MessageQueue::where('status', 'processing')
            ->where('claimed_at', '<', now()->subMinutes(5))
            ->update(['status' => 'pending', 'claimed_at' => null]);
    }

    /**
     * Clean old campaigns (completed/cancelled > 30 days) and their recipients.
     */
    private function cleanOldCampaigns(): int
    {
        $oldCampaigns = Campaign::whereIn('status', ['completed', 'cancelled'])
            ->where('updated_at', '<', now()->subDays(30))
            ->get();

        $count = 0;
        foreach ($oldCampaigns as $campaign) {
            $campaign->recipients()->delete();
            $campaign->delete();
            $count++;
        }

        return $count;
    }
}

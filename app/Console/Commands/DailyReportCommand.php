<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminConfig;
use App\Models\MessageQueue;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\OnboardingLink;
use App\Services\MessageQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class DailyReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:daily-report';

    /**
     * The console command description.
     */
    protected $description = 'Generate and send the daily Telegram report';

    /**
     * Execute the console command.
     */
    public function handle(Nutgram $bot): int
    {
        $this->info('Generating daily report...');

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Gather stats
        $stats = [
            'date' => now()->format('d/m/Y'),
            'notifications_sent' => NotificationLog::sent()
                ->betweenDates($yesterday, $today)
                ->count(),
            'notifications_failed' => NotificationLog::failed()
                ->betweenDates($yesterday, $today)
                ->count(),
            'messages_queued' => MessageQueue::whereDate('created_at', $today)->count(),
            'messages_sent' => MessageQueue::sent()->whereDate('sent_at', $today)->count(),
            'messages_dead' => MessageQueue::dead()->whereDate('updated_at', $today)->count(),
            'new_onboarding_links' => OnboardingLink::whereDate('created_at', $today)->count(),
            'onboarding_linked' => OnboardingLink::linked()->whereDate('linked_at', $today)->count(),
        ];

        // Output to console
        $this->table(
            ['Metric', 'Value'],
            collect($stats)->map(fn ($value, $key) => [$key, $value])->toArray()
        );

        // Try to find and send report via template
        $template = NotificationTemplate::getBest('daily_report', 'fr');

        if ($template) {
            $message = $template->render([
                'DATE' => $stats['date'],
                'DAILY_CA' => '—',
                'DAILY_COMMISSION' => '—',
                'REGISTRATION_COUNT' => $stats['new_onboarding_links'],
                'CALL_COUNT' => '—',
                'NOTIFICATIONS_SENT' => $stats['notifications_sent'],
                'NOTIFICATIONS_FAILED' => $stats['notifications_failed'],
                'MESSAGES_QUEUED' => $stats['messages_queued'],
                'MESSAGES_SENT' => $stats['messages_sent'],
                'MESSAGES_DEAD' => $stats['messages_dead'],
                'ONBOARDING_LINKED' => $stats['onboarding_linked'],
            ]);

            $this->line('');
            $this->line($message);

            // Send via Telegram
            $adminConfig = AdminConfig::current();
            if ($adminConfig->recipient_chat_id) {
                app(MessageQueueService::class)->enqueue(
                    chatId: $adminConfig->recipient_chat_id,
                    message: $message,
                    source: 'daily_report',
                );
                $this->info('Report queued for Telegram delivery');
            } else {
                $this->warn('No admin chat ID configured, report not sent');
            }
        }

        Log::info('telegram:daily-report completed', $stats);
        $this->info('Daily report generated successfully.');

        return self::SUCCESS;
    }
}

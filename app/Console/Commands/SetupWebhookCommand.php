<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use Throwable;

class SetupWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:setup-webhook {--url= : Webhook URL}';

    /**
     * The console command description.
     */
    protected $description = 'Register the Telegram Bot webhook URL';

    /**
     * Execute the console command.
     */
    public function handle(Nutgram $bot): int
    {
        $url = $this->option('url') ?? config('telegram.webhook_url');

        if (empty($url)) {
            $this->error('No webhook URL provided. Use --url or set TELEGRAM_WEBHOOK_URL in .env');

            return self::FAILURE;
        }

        $secretToken = config('telegram.webhook_secret');

        $this->info("Setting webhook to: {$url}");

        if ($secretToken) {
            $this->info('Using secret token for verification.');
        }

        try {
            $bot->setWebhook(
                url: $url,
                secret_token: $secretToken ?: null,
            );

            $this->info('Webhook registered successfully!');

            // Verify by getting webhook info
            $info = $bot->getWebhookInfo();
            $this->table(
                ['Property', 'Value'],
                [
                    ['URL', $info->url ?? '—'],
                    ['Pending updates', $info->pending_update_count ?? 0],
                    ['Has secret token', ! empty($info->has_custom_certificate) ? 'Yes' : 'No'],
                    ['Last error', $info->last_error_message ?? 'None'],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed to set webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}

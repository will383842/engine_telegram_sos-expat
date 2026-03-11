<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminConfig;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\TelegramBot;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected MessageQueueService $messageQueue,
        protected TemplateRenderer $renderer,
    ) {}

    /**
     * Send a notification for a given event type.
     *
     * Iterates over ALL active bots that have this event enabled,
     * fetches the best template, renders it, and enqueues the message for each bot.
     *
     * @param string $eventType One of the configured event types
     * @param array<string, mixed> $variables Template variables
     * @param array{minDuration?: int, minAmount?: int, allowedRoles?: string[]}|null $filters Optional filters
     */
    public function sendNotification(string $eventType, array $variables, ?array $filters = null): bool
    {
        try {
            // Apply filters before iterating bots
            if ($filters && !$this->passesFilters($variables, $filters)) {
                $this->logNotification($eventType, '', null, 'filtered', 'Did not pass filters', $variables, $filters);
                return false;
            }

            // Get all active bots that have this event enabled
            $bots = TelegramBot::forEvent($eventType);

            // Fallback: if no bots configured yet, use legacy AdminConfig
            if ($bots->isEmpty()) {
                return $this->sendViaLegacyConfig($eventType, $variables, $filters);
            }

            $sent = false;

            foreach ($bots as $bot) {
                /** @var TelegramBot $bot */
                $chatId = $bot->recipient_chat_id;

                if (empty($chatId)) {
                    Log::warning('NotificationService: No recipient chat_id for bot', ['bot_slug' => $bot->slug]);
                    $this->logNotification($eventType, '', null, 'filtered', "No chat_id for bot {$bot->slug}", $variables, $filters, $bot->slug);
                    continue;
                }

                // Get best template (French first, then English fallback)
                $template = NotificationTemplate::getBest($eventType, 'fr');

                if (!$template) {
                    Log::warning('NotificationService: No template found', ['event_type' => $eventType, 'bot_slug' => $bot->slug]);
                    $this->logNotification($eventType, $chatId, null, 'failed', 'No template found', $variables, $filters, $bot->slug);
                    continue;
                }

                // Render template
                $message = $this->renderer->render($template->template, $variables);

                // Enqueue message with bot_slug
                $this->messageQueue->enqueue(
                    chatId: $chatId,
                    message: $message,
                    source: $eventType,
                    parseMode: 'HTML',
                    botSlug: $bot->slug,
                );

                $this->logNotification($eventType, $chatId, $message, 'sent', null, $variables, $filters, $bot->slug);
                $sent = true;
            }

            return $sent;
        } catch (\Throwable $e) {
            Log::error('NotificationService: Failed to send notification', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            $this->logNotification($eventType, '', null, 'failed', $e->getMessage(), $variables, $filters);

            return false;
        }
    }

    /**
     * Legacy fallback: use AdminConfig (singleton) when no telegram_bots are configured.
     * This ensures backward compatibility during migration.
     */
    protected function sendViaLegacyConfig(string $eventType, array $variables, ?array $filters): bool
    {
        $adminConfig = AdminConfig::first();

        if (!$adminConfig) {
            Log::warning('NotificationService: No admin config found (legacy fallback)');
            $this->logNotification($eventType, '', null, 'filtered', 'No admin config', $variables, $filters);
            return false;
        }

        $notifications = $adminConfig->notifications ?? [];
        if (is_string($notifications)) {
            $notifications = json_decode($notifications, true) ?? [];
        }

        if (!($notifications[$eventType] ?? false)) {
            $this->logNotification($eventType, $adminConfig->recipient_chat_id ?? '', null, 'filtered', 'Event disabled (legacy)', $variables, $filters);
            return false;
        }

        $chatId = $adminConfig->recipient_chat_id;

        if (empty($chatId)) {
            $this->logNotification($eventType, '', null, 'filtered', 'No chat_id configured (legacy)', $variables, $filters);
            return false;
        }

        $template = NotificationTemplate::getBest($eventType, 'fr');

        if (!$template) {
            $this->logNotification($eventType, $chatId, null, 'failed', 'No template found', $variables, $filters);
            return false;
        }

        $message = $this->renderer->render($template->template, $variables);

        $this->messageQueue->enqueue(
            chatId: $chatId,
            message: $message,
            source: $eventType,
            botSlug: 'main',
        );

        $this->logNotification($eventType, $chatId, $message, 'sent', null, $variables, $filters, 'main');

        return true;
    }

    /**
     * Check if variables pass the given filters.
     */
    protected function passesFilters(array $variables, array $filters): bool
    {
        if (isset($filters['minDuration'])) {
            $duration = (int) ($variables['DURATION'] ?? $variables['duration'] ?? 0);
            if ($duration < $filters['minDuration']) {
                return false;
            }
        }

        if (isset($filters['minAmount'])) {
            $amount = (int) ($variables['AMOUNT'] ?? $variables['amount'] ?? 0);
            if ($amount < $filters['minAmount']) {
                return false;
            }
        }

        if (isset($filters['allowedRoles']) && is_array($filters['allowedRoles'])) {
            $role = $variables['ROLE'] ?? $variables['role'] ?? null;
            if ($role && !in_array($role, $filters['allowedRoles'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log a notification attempt.
     */
    protected function logNotification(
        string $eventType,
        string $chatId,
        ?string $message,
        string $status,
        ?string $error,
        ?array $variables,
        ?array $filters,
        ?string $botSlug = null,
    ): void {
        try {
            NotificationLog::create([
                'event_type' => $eventType,
                'chat_id' => $chatId,
                'message' => $message,
                'status' => $status,
                'error' => $error,
                'variables' => $variables,
                'filters' => $filters,
                'bot_slug' => $botSlug ?? 'main',
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationService: Failed to write log', ['error' => $e->getMessage()]);
        }
    }
}

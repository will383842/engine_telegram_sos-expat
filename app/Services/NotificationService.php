<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminConfig;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
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
     * Reads AdminConfig to check if the event is enabled, fetches the best template,
     * renders it with variables, and enqueues the message.
     *
     * @param string $eventType One of the configured event types
     * @param array<string, mixed> $variables Template variables
     * @param array{minDuration?: int, minAmount?: int, allowedRoles?: string[]}|null $filters Optional filters
     */
    public function sendNotification(string $eventType, array $variables, ?array $filters = null): bool
    {
        try {
            // Get admin config
            $adminConfig = AdminConfig::first();

            if (!$adminConfig) {
                Log::warning('NotificationService: No admin config found');
                $this->logNotification($eventType, '', null, 'filtered', 'No admin config', $variables, $filters);
                return false;
            }

            // Check if event is enabled
            $notifications = $adminConfig->notifications ?? [];
            if (is_string($notifications)) {
                $notifications = json_decode($notifications, true) ?? [];
            }

            if (!($notifications[$eventType] ?? false)) {
                Log::info('NotificationService: Event type disabled', ['event_type' => $eventType]);
                $this->logNotification($eventType, $adminConfig->recipient_chat_id ?? '', null, 'filtered', 'Event disabled', $variables, $filters);
                return false;
            }

            $chatId = $adminConfig->recipient_chat_id;

            if (empty($chatId)) {
                Log::warning('NotificationService: No recipient chat_id configured');
                $this->logNotification($eventType, '', null, 'filtered', 'No chat_id configured', $variables, $filters);
                return false;
            }

            // Apply filters
            if ($filters) {
                if (!$this->passesFilters($variables, $filters)) {
                    $this->logNotification($eventType, $chatId, null, 'filtered', 'Did not pass filters', $variables, $filters);
                    return false;
                }
            }

            // Get best template (French first, then English fallback)
            $template = NotificationTemplate::getBest($eventType, 'fr');

            if (!$template) {
                Log::warning('NotificationService: No template found', ['event_type' => $eventType]);
                $this->logNotification($eventType, $chatId, null, 'failed', 'No template found', $variables, $filters);
                return false;
            }

            // Render template
            $message = $this->renderer->render($template->template, $variables);

            // Enqueue message
            $this->messageQueue->enqueue(
                chatId: $chatId,
                message: $message,
                source: $eventType,
            );

            // Log success
            $this->logNotification($eventType, $chatId, $message, 'sent', null, $variables, $filters);

            return true;
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
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationService: Failed to write log', ['error' => $e->getMessage()]);
        }
    }
}

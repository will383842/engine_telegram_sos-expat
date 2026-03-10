<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdminConfig;
use App\Services\NotificationService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $botService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Return the current admin configuration.
     *
     * Frontend expects: { exists: boolean, data: { recipientChatId, notifications, updatedAt, updatedBy } }
     */
    public function getConfig(): JsonResponse
    {
        $config = AdminConfig::current();

        return response()->json([
            'exists' => true,
            'data'   => [
                'recipientPhoneNumber' => $config->recipient_phone_number,
                'recipientChatId'      => $config->recipient_chat_id,
                'notifications'        => $config->notifications ?? [],
                'updatedAt'            => $config->updated_at?->toIso8601String(),
                'updatedBy'            => $config->updated_by,
            ],
        ]);
    }

    /**
     * Update the admin configuration.
     *
     * Frontend sends camelCase: { recipientChatId, notifications }
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipientPhoneNumber' => 'nullable|string|max:20',
            'recipientChatId'      => 'nullable|string|max:64',
            'notifications'        => 'nullable|array',
            'notifications.*'      => 'boolean',
        ]);

        $config = AdminConfig::current();

        if (array_key_exists('recipientPhoneNumber', $validated)) {
            $config->recipient_phone_number = $validated['recipientPhoneNumber'];
        }
        if (array_key_exists('recipientChatId', $validated)) {
            $config->recipient_chat_id = $validated['recipientChatId'];
        }
        if (array_key_exists('notifications', $validated)) {
            $config->notifications = $validated['notifications'];
        }

        $config->updated_by = $request->input('firebase_uid', 'admin');
        $config->save();

        return response()->json([
            'exists' => true,
            'data'   => [
                'recipientPhoneNumber' => $config->recipient_phone_number,
                'recipientChatId'      => $config->recipient_chat_id,
                'notifications'        => $config->notifications ?? [],
                'updatedAt'            => $config->updated_at?->toIso8601String(),
                'updatedBy'            => $config->updated_by,
            ],
        ]);
    }

    /**
     * Validate the Telegram bot token by calling getMe.
     *
     * Frontend expects: { ok: boolean, botUsername: string }
     */
    public function validateBot(): JsonResponse
    {
        try {
            $botInfo = $this->botService->validateBot();

            return response()->json([
                'ok'          => true,
                'botUsername'  => $botInfo['username'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the chat ID from recent /start messages sent to the bot.
     *
     * Frontend expects: { ok: boolean, chatId?: string, error?: string }
     */
    public function getChatId(): JsonResponse
    {
        $updates = $this->botService->getUpdates();

        $chatId = null;

        foreach ($updates as $update) {
            $text = $update['text'] ?? '';

            if (str_starts_with($text, '/start')) {
                $chatId = $update['chat_id'] ?? '';

                break;
            }
        }

        if ($chatId !== null && $chatId !== '') {
            $config = AdminConfig::current();
            $config->recipient_chat_id = $chatId;
            $config->save();

            return response()->json([
                'ok'     => true,
                'chatId' => $chatId,
            ]);
        }

        return response()->json([
            'ok'    => false,
            'error' => 'Chat ID non trouvé. Envoyez /start au bot d\'abord.',
        ]);
    }

    /**
     * Send a test notification to verify the bot configuration.
     *
     * Frontend sends: { eventType: string, message?: string }
     */
    public function sendTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eventType' => 'nullable|string|max:64',
            'message'   => 'nullable|string|max:4096',
        ]);

        $eventType = $validated['eventType'] ?? 'test';

        $variables = [
            'TEST'    => 'true',
            'DATE'    => now()->setTimezone('Europe/Paris')->format('d/m/Y H:i:s'),
            'MESSAGE' => $validated['message'] ?? 'Ceci est un message de test.',
        ];

        $this->notificationService->sendNotification($eventType, $variables);

        return response()->json(['success' => true]);
    }
}

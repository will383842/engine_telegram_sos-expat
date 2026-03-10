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
     */
    public function getConfig(): JsonResponse
    {
        $config = AdminConfig::current();

        return response()->json([
            'success' => true,
            'config'  => $config->toArray(),
        ]);
    }

    /**
     * Update the admin configuration.
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_phone_number' => 'nullable|string|max:20',
            'recipient_chat_id'      => 'nullable|string|max:64',
            'notifications'          => 'nullable|array',
            'notifications.*'        => 'boolean',
        ]);

        $config = AdminConfig::current();
        $config->fill($validated);
        $config->updated_by = $request->input('firebase_uid', 'admin');
        $config->save();

        return response()->json([
            'success' => true,
            'config'  => $config->fresh()->toArray(),
        ]);
    }

    /**
     * Validate the Telegram bot token by calling getMe.
     */
    public function validateBot(): JsonResponse
    {
        $botInfo = $this->botService->validateBot();

        return response()->json([
            'success' => true,
            'bot'     => $botInfo,
        ]);
    }

    /**
     * Get the chat ID from recent /start messages sent to the bot.
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
        }

        return response()->json([
            'success' => true,
            'chatId'  => $chatId,
        ]);
    }

    /**
     * Send a test notification to verify the bot configuration.
     */
    public function sendTest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'nullable|string|max:64',
            'message'    => 'nullable|string|max:4096',
        ]);

        $eventType = $validated['event_type'] ?? 'test';

        $variables = [
            'TEST'    => 'true',
            'DATE'    => now()->setTimezone('Europe/Paris')->format('d/m/Y H:i:s'),
            'MESSAGE' => $validated['message'] ?? 'Ceci est un message de test.',
        ];

        $this->notificationService->sendNotification($eventType, $variables);

        return response()->json(['success' => true]);
    }
}

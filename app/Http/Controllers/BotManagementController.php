<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BotManagementController extends Controller
{
    public function __construct(
        private readonly TelegramBotService $botService,
    ) {}

    /**
     * GET /api/admin/bots
     *
     * List all bots (active and inactive).
     * Token is masked in the response for security.
     */
    public function index(): JsonResponse
    {
        $bots = TelegramBot::orderBy('id')->get()->map(function (TelegramBot $bot) {
            return [
                'id'               => $bot->id,
                'slug'             => $bot->slug,
                'name'             => $bot->name,
                'token_masked'     => $this->maskToken($bot->token),
                'recipient_chat_id' => $bot->recipient_chat_id,
                'notifications'    => $bot->notifications ?? [],
                'is_active'        => $bot->is_active,
                'created_at'       => $bot->created_at?->toIso8601String(),
                'updated_at'       => $bot->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $bots,
        ]);
    }

    /**
     * PUT /api/admin/bots/{id}
     *
     * Update bot configuration (name, recipient_chat_id, notifications, is_active).
     * Token update is allowed but optional.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bot = TelegramBot::findOrFail($id);

        $validated = $request->validate([
            'name'              => 'sometimes|string|max:128',
            'token'             => 'sometimes|string|max:256',
            'recipient_chat_id' => 'nullable|string|max:64',
            'notifications'     => 'nullable|array',
            'notifications.*'   => 'boolean',
            'is_active'         => 'sometimes|boolean',
        ]);

        $bot->update($validated);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $bot->id,
                'slug'             => $bot->slug,
                'name'             => $bot->name,
                'token_masked'     => $this->maskToken($bot->token),
                'recipient_chat_id' => $bot->recipient_chat_id,
                'notifications'    => $bot->notifications ?? [],
                'is_active'        => $bot->is_active,
                'updated_at'       => $bot->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/admin/bots/{id}/validate
     *
     * Validate bot token by calling Telegram getMe API.
     */
    public function validateBot(int $id): JsonResponse
    {
        $bot = TelegramBot::findOrFail($id);

        try {
            $info = $this->botService->validateBotToken($bot->token);

            return response()->json([
                'ok'          => true,
                'botId'       => $info['id'],
                'botUsername'  => $info['username'],
                'botFirstName' => $info['first_name'],
            ]);
        } catch (\Throwable $e) {
            Log::error('BotManagement: validateBot failed', [
                'bot_id' => $id,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/admin/bots/{id}/test
     *
     * Send a test message to the bot's configured recipient chat.
     */
    public function test(int $id): JsonResponse
    {
        $bot = TelegramBot::findOrFail($id);

        if (empty($bot->recipient_chat_id)) {
            return response()->json([
                'success' => false,
                'error'   => 'No recipient_chat_id configured for this bot.',
            ], 422);
        }

        $now = now()->setTimezone('Europe/Paris')->format('d/m/Y H:i:s');
        $message = "<b>\u{2705} Test — {$bot->name}</b>\n\n"
            . "Ce message confirme que le bot <b>{$bot->slug}</b> fonctionne correctement.\n"
            . "Envoyé le {$now}";

        $nutgram = TelegramBotService::forToken($bot->token);
        $sent = $this->botService->sendMessageWithBot($nutgram, $bot->recipient_chat_id, $message);

        if ($sent) {
            return response()->json([
                'success' => true,
                'message' => "Test message sent to chat {$bot->recipient_chat_id}.",
            ]);
        }

        return response()->json([
            'success' => false,
            'error'   => 'Failed to send test message. Check logs for details.',
        ], 500);
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */

    /**
     * Mask a bot token for safe display (show first 8 and last 4 chars).
     */
    private function maskToken(string $token): string
    {
        $len = mb_strlen($token);

        if ($len <= 12) {
            return str_repeat('*', $len);
        }

        return mb_substr($token, 0, 8) . str_repeat('*', $len - 12) . mb_substr($token, -4);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Return notification logs with cursor-based pagination.
     *
     * Frontend expects: { logs: LogEntry[], nextCursor: string|null, count: number }
     * Query params: limit, eventType, status, startAfter (cursor = log id)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eventType'  => 'nullable|string|max:64',
            'status'     => 'nullable|string|in:sent,failed,filtered',
            'limit'      => 'nullable|integer|min:1|max:100',
            'startAfter' => 'nullable|string',
        ]);

        $query = NotificationLog::query()->orderByDesc('id');

        if (!empty($validated['eventType'])) {
            $query->byEventType($validated['eventType']);
        }

        if (!empty($validated['status'])) {
            $query->byStatus($validated['status']);
        }

        // Cursor-based pagination: startAfter = last seen ID
        if (!empty($validated['startAfter'])) {
            $query->where('id', '<', (int) $validated['startAfter']);
        }

        $limit = (int) ($validated['limit'] ?? 30);
        $logs = $query->limit($limit + 1)->get();

        $hasMore = $logs->count() > $limit;
        if ($hasMore) {
            $logs = $logs->take($limit);
        }

        $nextCursor = $hasMore && $logs->isNotEmpty()
            ? (string) $logs->last()->id
            : null;

        // Map to frontend LogEntry format (camelCase)
        $mapped = $logs->map(fn ($log) => [
            'id'                => (string) $log->id,
            'eventType'         => $log->event_type,
            'status'            => $log->status,
            'recipientChatId'   => $log->chat_id,
            'errorMessage'      => $log->error,
            'telegramMessageId' => null,
            'sentAt'            => $log->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'logs'       => $mapped->values(),
            'nextCursor' => $nextCursor,
            'count'      => $mapped->count(),
        ]);
    }
}

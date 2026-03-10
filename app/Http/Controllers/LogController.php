<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * Return paginated notification logs with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'nullable|string|max:64',
            'status'     => 'nullable|string|in:sent,failed,filtered',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $query = NotificationLog::query()->orderByDesc('created_at');

        if (!empty($validated['event_type'])) {
            $query->byEventType($validated['event_type']);
        }

        if (!empty($validated['status'])) {
            $query->byStatus($validated['status']);
        }

        if (!empty($validated['from']) && !empty($validated['to'])) {
            $query->betweenDates($validated['from'], $validated['to']);
        }

        $perPage = (int) ($validated['per_page'] ?? 25);
        $logs    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'logs'    => $logs,
        ]);
    }
}

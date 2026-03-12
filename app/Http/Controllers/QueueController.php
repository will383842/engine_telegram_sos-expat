<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MessageQueue;
use App\Services\MessageQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class QueueController extends Controller
{
    public function __construct(
        private readonly MessageQueueService $queueService,
    ) {}

    /**
     * Return queue statistics matching frontend QueueStats interface.
     *
     * Frontend expects: { queueDepth: { pending, sending, dead, total }, dailyStats: [...], hourlyCounts: {...} }
     */
    public function stats(): JsonResponse
    {
        $rawStats = $this->queueService->getStats();

        $pending = $rawStats['pending'] ?? 0;
        $processing = $rawStats['processing'] ?? 0;
        $dead = $rawStats['dead'] ?? 0;

        // Daily stats for last 7 days
        $dailyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $sent = MessageQueue::where('status', 'sent')
                ->whereDate('updated_at', $date)
                ->count();
            $failed = MessageQueue::where('status', 'failed')
                ->whereDate('updated_at', $date)
                ->count();
            $dailyStats[] = [
                'date'   => $date,
                'sent'   => $sent,
                'failed' => $failed,
            ];
        }

        // Hourly counts for today (EXTRACT works on both PostgreSQL and MySQL)
        $hourlyCounts = [];
        $todayMessages = MessageQueue::whereDate('created_at', Carbon::today())
            ->selectRaw('EXTRACT(HOUR FROM created_at)::int as hour, count(*) as count')
            ->groupByRaw('EXTRACT(HOUR FROM created_at)')
            ->pluck('count', 'hour')
            ->toArray();

        for ($h = 0; $h < 24; $h++) {
            $hourlyCounts[(string) $h] = $todayMessages[$h] ?? 0;
        }

        return response()->json([
            'queueDepth' => [
                'pending' => $pending,
                'sending' => $processing,
                'dead'    => $dead,
                'total'   => $pending + $processing + $dead,
            ],
            'dailyStats'  => $dailyStats,
            'hourlyCounts' => $hourlyCounts,
        ]);
    }

    /**
     * Reprocess dead-letter messages by resetting them to pending.
     */
    public function reprocess(): JsonResponse
    {
        $count = $this->queueService->reprocessDeadLetters();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }
}

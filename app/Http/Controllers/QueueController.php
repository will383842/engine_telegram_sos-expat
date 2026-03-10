<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MessageQueueService;
use Illuminate\Http\JsonResponse;

class QueueController extends Controller
{
    public function __construct(
        private readonly MessageQueueService $queueService,
    ) {}

    /**
     * Return queue statistics (pending, processing, sent, failed, dead counts).
     */
    public function stats(): JsonResponse
    {
        $stats = $this->queueService->getStats();

        return response()->json([
            'success' => true,
            'stats'   => $stats,
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

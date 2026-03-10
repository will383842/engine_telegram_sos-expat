<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SubscriberStatsService;
use Illuminate\Http\JsonResponse;

class SubscriberController extends Controller
{
    public function __construct(
        private readonly SubscriberStatsService $statsService,
    ) {}

    /**
     * Return subscriber statistics.
     *
     * Frontend expects: { total: number, breakdown: Record<string, number>, countedAt?: string }
     */
    public function stats(): JsonResponse
    {
        $stats = $this->statsService->getStats();

        return response()->json([
            'total'     => $stats['total_subscribers'] ?? 0,
            'breakdown' => $stats['by_role'] ?? [],
            'countedAt' => now()->toIso8601String(),
        ]);
    }
}

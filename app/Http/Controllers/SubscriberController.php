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
     * Return subscriber statistics from local DB and Firestore.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->statsService->getStats();

        return response()->json([
            'success' => true,
            'stats'   => $stats,
        ]);
    }
}

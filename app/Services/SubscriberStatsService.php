<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OnboardingLink;
use App\Models\SubscriberStat;

class SubscriberStatsService
{
    public function getStats(): array
    {
        $today = SubscriberStat::today();

        $totalLinked = OnboardingLink::linked()->count();
        $byRole = OnboardingLink::linked()
            ->selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        return [
            'total_subscribers' => $totalLinked,
            'by_role' => $byRole,
            'today' => [
                'messages_sent' => $today->messages_sent,
                'messages_failed' => $today->messages_failed,
                'success_rate' => $today->success_rate,
            ],
        ];
    }
}

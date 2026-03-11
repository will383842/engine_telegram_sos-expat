<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Google\Cloud\Core\Timestamp;
use Illuminate\Support\Facades\Log;

class DailyReportService
{
    public function __construct(
        protected FirestoreService $firestoreService,
        protected NotificationService $notificationService,
        protected TemplateRenderer $renderer,
    ) {}

    /**
     * Generate and send the daily report.
     *
     * Reads Firestore collections (payments, users, call_sessions) for the last 24h
     * using Paris timezone, then sends via the notification system.
     */
    public function generate(): void
    {
        $timezone = config('telegram.timezone', 'Europe/Paris');
        $now = Carbon::now($timezone);
        $yesterday = $now->copy()->subDay()->startOfDay();
        $todayStart = $now->copy()->startOfDay();

        Log::info('DailyReportService: Generating report', [
            'from' => $yesterday->toIso8601String(),
            'to' => $todayStart->toIso8601String(),
        ]);

        try {
            // Fetch data from Firestore
            $dailyCA = $this->calculateDailyCA($yesterday, $todayStart);
            $commissions = $this->calculateCommissions($yesterday, $todayStart);
            $registrations = $this->countRegistrations($yesterday, $todayStart);
            $calls = $this->countCalls($yesterday, $todayStart);

            // Build variables for the template
            $variables = [
                'DATE' => $this->renderer->formatDate($yesterday),
                'DAILY_CA' => $this->renderer->formatMoney($dailyCA),
                'DAILY_CA_CENTS' => (string) $dailyCA,
                'COMMISSIONS' => $this->renderer->formatMoney($commissions),
                'COMMISSIONS_CENTS' => (string) $commissions,
                'NEW_REGISTRATIONS' => (string) $registrations['total'],
                'NEW_CLIENTS' => (string) ($registrations['clients'] ?? 0),
                'NEW_PROVIDERS' => (string) ($registrations['providers'] ?? 0),
                'NEW_AFFILIATES' => (string) ($registrations['affiliates'] ?? 0),
                'TOTAL_CALLS' => (string) ($calls['total'] ?? 0),
                'COMPLETED_CALLS' => (string) ($calls['completed'] ?? 0),
                'MISSED_CALLS' => (string) ($calls['missed'] ?? 0),
                'AVG_DURATION' => (string) ($calls['avgDuration'] ?? 0),
            ];

            // Send via notification system
            $this->notificationService->sendNotification('daily_report', $variables);

            Log::info('DailyReportService: Report sent successfully', $variables);
        } catch (\Throwable $e) {
            Log::error('DailyReportService: Failed to generate report', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate daily revenue from payments collection.
     *
     * @return int Amount in cents
     */
    protected function calculateDailyCA(Carbon $from, Carbon $to): int
    {
        try {
            $payments = $this->firestoreService->queryCollection('payments', [
                ['field' => 'createdAt', 'operator' => '>=', 'value' => new Timestamp($from->toDateTime())],
                ['field' => 'createdAt', 'operator' => '<', 'value' => new Timestamp($to->toDateTime())],
                ['field' => 'status', 'operator' => '==', 'value' => 'succeeded'],
            ]);

            $total = 0;
            foreach ($payments as $payment) {
                $total += (int) ($payment['amount'] ?? 0);
            }

            return $total;
        } catch (\Throwable $e) {
            Log::warning('DailyReportService: Failed to calculate CA', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Calculate daily commissions paid.
     *
     * @return int Amount in cents
     */
    protected function calculateCommissions(Carbon $from, Carbon $to): int
    {
        try {
            $commissions = $this->firestoreService->queryCollection('affiliate_notifications', [
                ['field' => 'createdAt', 'operator' => '>=', 'value' => new Timestamp($from->toDateTime())],
                ['field' => 'createdAt', 'operator' => '<', 'value' => new Timestamp($to->toDateTime())],
            ]);

            $total = 0;
            foreach ($commissions as $commission) {
                $total += (int) ($commission['amount'] ?? 0);
            }

            return $total;
        } catch (\Throwable $e) {
            Log::warning('DailyReportService: Failed to calculate commissions', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Count new registrations by type.
     *
     * @return array{total: int, clients: int, providers: int, affiliates: int}
     */
    protected function countRegistrations(Carbon $from, Carbon $to): array
    {
        try {
            $users = $this->firestoreService->queryCollection('users', [
                ['field' => 'createdAt', 'operator' => '>=', 'value' => new Timestamp($from->toDateTime())],
                ['field' => 'createdAt', 'operator' => '<', 'value' => new Timestamp($to->toDateTime())],
            ]);

            $counts = ['total' => 0, 'clients' => 0, 'providers' => 0, 'affiliates' => 0];

            foreach ($users as $user) {
                $counts['total']++;
                $role = $user['role'] ?? 'client';

                if (in_array($role, ['chatter', 'influencer', 'blogger', 'groupAdmin', 'group_admin', 'captain', 'captain_chatter', 'partner'], true)) {
                    $counts['affiliates']++;
                } elseif (in_array($role, ['provider', 'lawyer', 'expat_expert'], true)) {
                    $counts['providers']++;
                } else {
                    $counts['clients']++;
                }
            }

            return $counts;
        } catch (\Throwable $e) {
            Log::warning('DailyReportService: Failed to count registrations', ['error' => $e->getMessage()]);
            return ['total' => 0, 'clients' => 0, 'providers' => 0, 'affiliates' => 0];
        }
    }

    /**
     * Count calls from call_sessions collection.
     *
     * @return array{total: int, completed: int, missed: int, avgDuration: int}
     */
    protected function countCalls(Carbon $from, Carbon $to): array
    {
        try {
            $sessions = $this->firestoreService->queryCollection('call_sessions', [
                ['field' => 'createdAt', 'operator' => '>=', 'value' => new Timestamp($from->toDateTime())],
                ['field' => 'createdAt', 'operator' => '<', 'value' => new Timestamp($to->toDateTime())],
            ]);

            $total = 0;
            $completed = 0;
            $missed = 0;
            $totalDuration = 0;

            foreach ($sessions as $session) {
                $total++;
                $status = $session['status'] ?? '';

                if ($status === 'completed') {
                    $completed++;
                    $totalDuration += (int) ($session['duration'] ?? 0);
                } elseif (in_array($status, ['missed', 'no_answer', 'failed'], true)) {
                    $missed++;
                }
            }

            return [
                'total' => $total,
                'completed' => $completed,
                'missed' => $missed,
                'avgDuration' => $completed > 0 ? (int) round($totalDuration / $completed) : 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('DailyReportService: Failed to count calls', ['error' => $e->getMessage()]);
            return ['total' => 0, 'completed' => 0, 'missed' => 0, 'avgDuration' => 0];
        }
    }
}

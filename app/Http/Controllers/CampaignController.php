<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Return list of campaigns as flat array.
     *
     * Frontend expects: { campaigns: Campaign[] }
     * Query params: status, limit (default 50)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,processing,completed,cancelled',
            'limit'  => 'nullable|integer|min:1|max:200',
        ]);

        $query = Campaign::query()->orderByDesc('created_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $limit = (int) ($validated['limit'] ?? 50);
        $campaigns = $query->limit($limit)->get();

        // Map to frontend Campaign interface (camelCase)
        $mapped = $campaigns->map(fn ($c) => [
            'id'             => (string) $c->id,
            'name'           => $c->name,
            'targetAudience' => $c->filters['role'] ?? 'all',
            'targetCount'    => $c->total_recipients,
            'sentCount'      => $c->sent_count,
            'failedCount'    => $c->failed_count,
            'status'         => $c->status,
            'scheduledAt'    => $c->scheduled_at?->toIso8601String(),
            'completedAt'    => $c->completed_at?->toIso8601String(),
            'createdAt'      => $c->created_at?->toIso8601String(),
            'createdBy'      => $c->created_by ?? 'admin',
        ]);

        return response()->json([
            'campaigns' => $mapped->values(),
        ]);
    }

    /**
     * Create a new campaign.
     *
     * Frontend sends: { name, message, targetAudience, scheduledAt? }
     * Frontend expects: { ok: boolean, campaignId: string, targetCount: number }
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'message'        => 'required|string|max:4096',
            'targetAudience' => 'nullable|string|max:64',
            'parseMode'      => 'nullable|string|in:HTML,MarkdownV2',
            'scheduledAt'    => 'nullable|date|after:now',
        ]);

        $filters = null;
        $targetAudience = $validated['targetAudience'] ?? 'all';
        if ($targetAudience !== 'all') {
            $filters = ['role' => $targetAudience];
        }

        $campaign = Campaign::create([
            'name'             => $validated['name'],
            'message'          => $validated['message'],
            'parse_mode'       => $validated['parseMode'] ?? 'HTML',
            'status'           => 'pending',
            'total_recipients' => 0,
            'sent_count'       => 0,
            'failed_count'     => 0,
            'created_by'       => $request->input('firebase_uid', 'admin'),
            'filters'          => $filters,
            'scheduled_at'     => $validated['scheduledAt'] ?? null,
        ]);

        return response()->json([
            'ok'          => true,
            'campaignId'  => (string) $campaign->id,
            'targetCount' => $campaign->total_recipients,
        ], 201);
    }

    /**
     * Show a single campaign with recipient stats.
     */
    public function show(int $id): JsonResponse
    {
        $campaign = Campaign::with([
            'recipients' => fn ($q) => $q->select('id', 'campaign_id', 'chat_id', 'status', 'sent_at'),
        ])->findOrFail($id);

        $recipientStats = [
            'total'   => $campaign->recipients->count(),
            'sent'    => $campaign->recipients->where('status', 'sent')->count(),
            'failed'  => $campaign->recipients->where('status', 'failed')->count(),
            'pending' => $campaign->recipients->where('status', 'pending')->count(),
        ];

        return response()->json([
            'success'        => true,
            'campaign'       => $campaign->toArray(),
            'recipientStats' => $recipientStats,
        ]);
    }

    /**
     * Cancel a pending or processing campaign.
     */
    public function cancel(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        if (!in_array($campaign->status, ['pending', 'processing'], true)) {
            return response()->json([
                'success' => false,
                'error'   => "Cannot cancel a campaign with status '{$campaign->status}'.",
            ], 422);
        }

        $campaign->update([
            'status'       => 'cancelled',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * Return paginated list of campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'   => 'nullable|string|in:pending,processing,completed,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Campaign::query()->orderByDesc('created_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage   = (int) ($validated['per_page'] ?? 25);
        $campaigns = $query->paginate($perPage);

        return response()->json([
            'success'   => true,
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Create a new campaign.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'message'      => 'required|string|max:4096',
            'parse_mode'   => 'nullable|string|in:HTML,MarkdownV2',
            'filters'      => 'nullable|array',
            'filters.role' => 'nullable|string|in:chatter,influencer,blogger,group_admin',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $campaign = Campaign::create([
            'name'             => $validated['name'],
            'message'          => $validated['message'],
            'parse_mode'       => $validated['parse_mode'] ?? 'HTML',
            'status'           => 'pending',
            'total_recipients' => 0,
            'sent_count'       => 0,
            'failed_count'     => 0,
            'created_by'       => $request->input('firebase_uid', 'admin'),
            'filters'          => $validated['filters'] ?? null,
            'scheduled_at'     => $validated['scheduled_at'] ?? null,
        ]);

        return response()->json([
            'success'  => true,
            'campaign' => $campaign->toArray(),
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
            'total'  => $campaign->recipients->count(),
            'sent'   => $campaign->recipients->where('status', 'sent')->count(),
            'failed' => $campaign->recipients->where('status', 'failed')->count(),
            'pending' => $campaign->recipients->where('status', 'pending')->count(),
        ];

        return response()->json([
            'success'         => true,
            'campaign'        => $campaign->toArray(),
            'recipientStats'  => $recipientStats,
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
            'success'  => true,
            'campaign' => $campaign->fresh()->toArray(),
        ]);
    }
}

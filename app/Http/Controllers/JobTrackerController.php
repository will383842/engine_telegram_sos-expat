<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\JobAd;
use App\Models\JobSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobTrackerController extends Controller
{
    /* =====================================================================
     * ADS — CRUD
     * =================================================================== */

    public function listAds(Request $request): JsonResponse
    {
        $query = JobAd::query();

        // Include trashed if requested
        if ($request->boolean('trashed')) {
            $query = JobAd::onlyTrashed();
        }

        // Filters
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($lang = $request->input('lang')) {
            $query->where('lang', $lang);
        }
        if ($country = $request->input('country')) {
            $query->where('country', $country);
        }
        if ($poster = $request->input('poster')) {
            $query->where('poster', $poster);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('site', 'ilike', "%{$search}%")
                  ->orWhere('country', 'ilike', "%{$search}%")
                  ->orWhere('category', 'ilike', "%{$search}%")
                  ->orWhere('notes', 'ilike', "%{$search}%")
                  ->orWhere('poster', 'ilike', "%{$search}%");
            });
        }

        $ads = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => $ads]);
    }

    public function createAd(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'site' => 'nullable|string|max:255',
            'site_url' => 'nullable|url|max:2000',
            'ad_url' => 'nullable|url|max:2000',
            'country' => 'nullable|string|max:100',
            'lang' => 'nullable|string|max:5',
            'status' => 'nullable|string|in:draft,pending,approved,active,expired,rejected',
            'expiry' => 'nullable|date',
            'category' => 'nullable|string|max:100',
            'login' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'responses' => 'nullable|integer|min:0',
            'response_history' => 'nullable|array',
            'pub_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'poster' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'audit' => 'nullable|array',
        ]);

        $ad = JobAd::create($validated);

        return response()->json(['success' => true, 'data' => $ad], 201);
    }

    public function updateAd(Request $request, string $id): JsonResponse
    {
        $ad = JobAd::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'site' => 'nullable|string|max:255',
            'site_url' => 'nullable|url|max:2000',
            'ad_url' => 'nullable|url|max:2000',
            'country' => 'nullable|string|max:100',
            'lang' => 'nullable|string|max:5',
            'status' => 'nullable|string|in:draft,pending,approved,active,expired,rejected',
            'expiry' => 'nullable|date',
            'category' => 'nullable|string|max:100',
            'login' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'responses' => 'nullable|integer|min:0',
            'response_history' => 'nullable|array',
            'pub_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'poster' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'audit' => 'nullable|array',
        ]);

        $ad->update($validated);

        return response()->json(['success' => true, 'data' => $ad->fresh()]);
    }

    public function trashAd(Request $request, string $id): JsonResponse
    {
        $ad = JobAd::findOrFail($id);
        $ad->deleted_by = $request->input('operator', 'system');
        $ad->save();
        $ad->delete(); // soft delete

        return response()->json(['success' => true]);
    }

    public function restoreAd(string $id): JsonResponse
    {
        $ad = JobAd::onlyTrashed()->findOrFail($id);
        $ad->deleted_by = null;
        $ad->save();
        $ad->restore();

        return response()->json(['success' => true, 'data' => $ad->fresh()]);
    }

    public function destroyAd(string $id): JsonResponse
    {
        $ad = JobAd::onlyTrashed()->findOrFail($id);
        $ad->forceDelete();

        return response()->json(['success' => true]);
    }

    public function emptyTrash(): JsonResponse
    {
        $count = JobAd::onlyTrashed()->count();
        JobAd::onlyTrashed()->forceDelete();

        return response()->json(['success' => true, 'deleted' => $count]);
    }

    /* =====================================================================
     * ADS — BULK OPERATIONS
     * =================================================================== */

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
            'status' => 'required|string|in:draft,pending,approved,active,expired,rejected',
        ]);

        $count = JobAd::whereIn('id', $request->input('ids'))
            ->update(['status' => $request->input('status'), 'updated_at' => now()]);

        return response()->json(['success' => true, 'updated' => $count]);
    }

    public function bulkTrash(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'uuid',
        ]);

        $operator = $request->input('operator', 'system');

        JobAd::whereIn('id', $request->input('ids'))
            ->update(['deleted_by' => $operator, 'deleted_at' => now()]);

        return response()->json(['success' => true]);
    }

    /* =====================================================================
     * ADS — IMPORT (bulk create/upsert)
     * =================================================================== */

    public function importAds(Request $request): JsonResponse
    {
        $request->validate([
            'ads' => 'required|array|min:1',
            'ads.*.title' => 'required|string|max:500',
        ]);

        $created = 0;
        $updated = 0;

        foreach ($request->input('ads') as $adData) {
            // If ID provided and exists, update. Otherwise create.
            if (!empty($adData['id'])) {
                $existing = JobAd::withTrashed()->find($adData['id']);
                if ($existing) {
                    $existing->update($adData);
                    $updated++;
                    continue;
                }
            }

            // Generate UUID if not provided
            if (empty($adData['id'])) {
                $adData['id'] = Str::uuid()->toString();
            }

            JobAd::create($adData);
            $created++;
        }

        return response()->json(['success' => true, 'created' => $created, 'updated' => $updated]);
    }

    /* =====================================================================
     * SITES — CRUD
     * =================================================================== */

    public function listSites(): JsonResponse
    {
        $sites = JobSite::orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $sites]);
    }

    public function createSite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:2000',
            'country' => 'nullable|string|max:100',
            'lang' => 'nullable|string|max:5',
            'category' => 'nullable|string|max:100',
            'preapproval' => 'nullable|string|in:no,yes,auto',
            'validity' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'login' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_paid' => 'nullable|boolean',
            'paid_detected_at' => 'nullable|date',
            'audit' => 'nullable|array',
        ]);

        $site = JobSite::create($validated);

        return response()->json(['success' => true, 'data' => $site], 201);
    }

    public function updateSite(Request $request, string $id): JsonResponse
    {
        $site = JobSite::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'nullable|url|max:2000',
            'country' => 'nullable|string|max:100',
            'lang' => 'nullable|string|max:5',
            'category' => 'nullable|string|max:100',
            'preapproval' => 'nullable|string|in:no,yes,auto',
            'validity' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'login' => 'nullable|string|max:255',
            'password' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_paid' => 'nullable|boolean',
            'paid_detected_at' => 'nullable|date',
            'audit' => 'nullable|array',
        ]);

        $site->update($validated);

        return response()->json(['success' => true, 'data' => $site->fresh()]);
    }

    public function destroySite(string $id): JsonResponse
    {
        $site = JobSite::findOrFail($id);
        $site->delete();

        return response()->json(['success' => true]);
    }

    /* =====================================================================
     * STATS — Dashboard statistics
     * =================================================================== */

    public function stats(): JsonResponse
    {
        $totalAds = JobAd::count();
        $byStatus = JobAd::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
        $totalResponses = JobAd::sum('responses');
        $countries = JobAd::distinct('country')->count('country');
        $totalSites = JobSite::count();
        $trashCount = JobAd::onlyTrashed()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_ads' => $totalAds,
                'by_status' => $byStatus,
                'total_responses' => (int) $totalResponses,
                'countries' => $countries,
                'total_sites' => $totalSites,
                'trash_count' => $trashCount,
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingResource;
use App\Models\MarketingUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminResourceController extends Controller
{
    /**
     * GET /api/admin/marketing/resources
     * List all resources with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MarketingResource::query()->ordered();

        if ($role = $request->query('role')) {
            $query->forRole($role);
        }

        if ($category = $request->query('category')) {
            $query->forCategory($category);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // active filter (default: all)
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search by name (any language)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                foreach (MarketingResource::LANGUAGES as $lang) {
                    $q->orWhereRaw("name->>? ILIKE ?", [$lang, "%{$search}%"]);
                }
            });
        }

        $resources = $query->get()->map(fn (MarketingResource $r) => $r->toAdmin());

        return response()->json([
            'success'   => true,
            'resources' => $resources,
            'count'     => $resources->count(),
        ]);
    }

    /**
     * POST /api/admin/marketing/resources
     * Create a new resource.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_roles'   => 'required|array|min:1',
            'target_roles.*' => 'in:' . implode(',', MarketingResource::ROLES),
            'category'       => 'required|in:' . implode(',', MarketingResource::CATEGORIES),
            'type'           => 'required|in:' . implode(',', MarketingResource::TYPES),
            'name'           => 'required|array',
            'name.fr'        => 'required|string|max:255',
            'name.en'        => 'required|string|max:255',
            'description'    => 'nullable|array',
            'content'        => 'nullable|array',
            'file_path'      => 'nullable|string|max:500',
            'thumbnail_path' => 'nullable|string|max:500',
            'file_format'    => 'nullable|string|max:20',
            'file_size'      => 'nullable|integer|min:0',
            'placeholders'   => 'nullable|array',
            'seo_keywords'   => 'nullable|array',
            'word_count'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer|min:0',
        ]);

        $resource = MarketingResource::create($validated);

        Log::info('Marketing resource created', [
            'id'       => $resource->id,
            'category' => $resource->category,
            'type'     => $resource->type,
            'roles'    => $resource->target_roles,
            'admin'    => $request->input('firebase_uid'),
        ]);

        return response()->json([
            'success'  => true,
            'resource' => $resource->toAdmin(),
        ], 201);
    }

    /**
     * PUT /api/admin/marketing/resources/{id}
     * Update an existing resource.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $resource = MarketingResource::findOrFail($id);

        $validated = $request->validate([
            'target_roles'   => 'sometimes|array|min:1',
            'target_roles.*' => 'in:' . implode(',', MarketingResource::ROLES),
            'category'       => 'sometimes|in:' . implode(',', MarketingResource::CATEGORIES),
            'type'           => 'sometimes|in:' . implode(',', MarketingResource::TYPES),
            'name'           => 'sometimes|array',
            'description'    => 'nullable|array',
            'content'        => 'nullable|array',
            'file_path'      => 'nullable|string|max:500',
            'thumbnail_path' => 'nullable|string|max:500',
            'file_format'    => 'nullable|string|max:20',
            'file_size'      => 'nullable|integer|min:0',
            'placeholders'   => 'nullable|array',
            'seo_keywords'   => 'nullable|array',
            'word_count'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer|min:0',
        ]);

        $resource->update($validated);

        return response()->json([
            'success'  => true,
            'resource' => $resource->fresh()->toAdmin(),
        ]);
    }

    /**
     * DELETE /api/admin/marketing/resources/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $resource = MarketingResource::findOrFail($id);

        // Delete associated file from storage if exists
        if ($resource->file_path && Storage::exists($resource->file_path)) {
            Storage::delete($resource->file_path);
        }
        if ($resource->thumbnail_path && Storage::exists($resource->thumbnail_path)) {
            Storage::delete($resource->thumbnail_path);
        }

        $resource->delete();

        Log::info('Marketing resource deleted', [
            'id'    => $id,
            'admin' => $request->input('firebase_uid'),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/admin/marketing/resources/upload
     * Upload a file and return its path.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        $path = $file->storeAs('marketing/resources', $filename, 'public');

        // Generate thumbnail for images
        $thumbnailPath = null;
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $thumbnailPath = $this->generateThumbnail($file, $filename);
        }

        return response()->json([
            'success'        => true,
            'file_path'      => $path,
            'thumbnail_path' => $thumbnailPath,
            'file_format'    => strtoupper($extension),
            'file_size'      => $file->getSize(),
            'mime_type'      => $file->getMimeType(),
        ]);
    }

    /**
     * PATCH /api/admin/marketing/resources/bulk
     * Bulk update (activate/deactivate/delete).
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'uuid',
            'action' => 'required|in:activate,deactivate,delete',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        $affected = match ($action) {
            'activate'   => MarketingResource::whereIn('id', $ids)->update(['is_active' => true]),
            'deactivate' => MarketingResource::whereIn('id', $ids)->update(['is_active' => false]),
            'delete'     => $this->bulkDelete($ids),
        };

        Log::info('Marketing resources bulk action', [
            'action'   => $action,
            'count'    => $affected,
            'admin'    => $request->input('firebase_uid'),
        ]);

        return response()->json([
            'success'  => true,
            'affected' => $affected,
        ]);
    }

    /**
     * PATCH /api/admin/marketing/resources/reorder
     * Update sort_order for multiple resources.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.id'         => 'required|uuid',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                MarketingResource::where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/admin/marketing/resources/stats
     * Usage analytics.
     */
    public function stats(Request $request): JsonResponse
    {
        // Top downloaded resources
        $topDownloads = MarketingResource::query()
            ->where('download_count', '>', 0)
            ->orderByDesc('download_count')
            ->limit(20)
            ->get(['id', 'name', 'category', 'type', 'target_roles', 'download_count', 'copy_count']);

        // Usage by role
        $usageByRole = MarketingUsageLog::query()
            ->select('user_role', 'action', DB::raw('COUNT(*) as count'))
            ->groupBy('user_role', 'action')
            ->get();

        // Usage trend (last 30 days)
        $usageTrend = MarketingUsageLog::query()
            ->select(DB::raw("DATE(created_at) as date"), 'action', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get();

        // Count by category
        $countByCategory = MarketingResource::query()
            ->select('category', DB::raw('COUNT(*) as count'))
            ->where('is_active', true)
            ->groupBy('category')
            ->get();

        return response()->json([
            'success'          => true,
            'top_downloads'    => $topDownloads,
            'usage_by_role'    => $usageByRole,
            'usage_trend'      => $usageTrend,
            'count_by_category' => $countByCategory,
            'total_resources'  => MarketingResource::count(),
            'active_resources' => MarketingResource::where('is_active', true)->count(),
        ]);
    }

    /**
     * Bulk delete with file cleanup.
     */
    private function bulkDelete(array $ids): int
    {
        $resources = MarketingResource::whereIn('id', $ids)->get();

        foreach ($resources as $resource) {
            if ($resource->file_path && Storage::disk('public')->exists($resource->file_path)) {
                Storage::disk('public')->delete($resource->file_path);
            }
            if ($resource->thumbnail_path && Storage::disk('public')->exists($resource->thumbnail_path)) {
                Storage::disk('public')->delete($resource->thumbnail_path);
            }
        }

        return MarketingResource::whereIn('id', $ids)->delete();
    }

    /**
     * Generate a thumbnail for an uploaded image (300px wide).
     */
    private function generateThumbnail($file, string $filename): ?string
    {
        try {
            $sourcePath = $file->getRealPath();
            $imageInfo = getimagesize($sourcePath);

            if (!$imageInfo) {
                return null;
            }

            $sourceImage = match ($imageInfo['mime']) {
                'image/jpeg' => imagecreatefromjpeg($sourcePath),
                'image/png'  => imagecreatefrompng($sourcePath),
                'image/webp' => imagecreatefromwebp($sourcePath),
                default      => null,
            };

            if (!$sourceImage) {
                return null;
            }

            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            if ($origWidth <= 0) {
                return null;
            }

            $thumbWidth = 300;
            $thumbHeight = (int) ($origHeight * ($thumbWidth / $origWidth));

            if ($thumbHeight <= 0) {
                return null;
            }

            $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // Preserve transparency for PNG
            if ($imageInfo['mime'] === 'image/png') {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
            }

            imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $origWidth, $origHeight);

            // Write thumbnail via Storage facade for consistency
            $thumbFilename = 'thumb_' . $filename;
            $thumbRelPath = "marketing/thumbnails/{$thumbFilename}";
            $tmpPath = tempnam(sys_get_temp_dir(), 'thumb_');

            match ($imageInfo['mime']) {
                'image/jpeg' => imagejpeg($thumbImage, $tmpPath, 80),
                'image/png'  => imagepng($thumbImage, $tmpPath, 8),
                'image/webp' => imagewebp($thumbImage, $tmpPath, 80),
                default      => null,
            };

            // Store via Storage facade (consistent with upload)
            Storage::disk('public')->put($thumbRelPath, file_get_contents($tmpPath));
            unlink($tmpPath);

            // GD objects are freed automatically in PHP 8.0+
            unset($sourceImage, $thumbImage);

            return $thumbRelPath;
        } catch (\Throwable $e) {
            Log::warning('Thumbnail generation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public controller (no authentication).
 * Serves resources tagged with "press" in target_roles.
 * Used by the future press landing page.
 */
class PressResourceController extends Controller
{
    /**
     * GET /api/press/resources
     *
     * Public endpoint — no auth required.
     * Language detected from Accept-Language header or ?lang= param.
     */
    public function index(Request $request): JsonResponse
    {
        $lang = $request->query('lang')
            ?? $this->parseAcceptLanguage($request->header('Accept-Language', 'en'));

        if (!in_array($lang, MarketingResource::LANGUAGES)) {
            $lang = 'en';
        }

        $query = MarketingResource::query()
            ->active()
            ->forRole('press')
            ->ordered();

        // Optional category filter
        if ($category = $request->query('category')) {
            $query->forCategory($category);
        }

        $resources = $query->get()->map(fn (MarketingResource $r) => [
            'id'          => $r->id,
            'category'    => $r->category,
            'type'        => $r->type,
            'name'        => $r->translate('name', $lang),
            'description' => $r->translate('description', $lang),
            'file_url'    => $r->file_path ? url("storage/{$r->file_path}") : null,
            'thumbnail'   => $r->thumbnail_path ? url("storage/{$r->thumbnail_path}") : null,
            'file_format' => $r->file_format,
            'file_size'   => $r->file_size,
        ]);

        return response()->json([
            'success'   => true,
            'resources' => $resources,
            'language'  => $lang,
        ]);
    }

    /**
     * GET /api/press/resources/{id}/download
     *
     * Public download — tracks download count (no user info).
     */
    public function download(string $id): JsonResponse
    {
        $resource = MarketingResource::query()
            ->active()
            ->forRole('press')
            ->findOrFail($id);

        if (!$resource->file_path) {
            return response()->json(['error' => 'No file available.'], 404);
        }

        $resource->increment('download_count');

        return response()->json([
            'success'     => true,
            'file_url'    => url("storage/{$resource->file_path}"),
            'file_format' => $resource->file_format,
            'file_size'   => $resource->file_size,
        ]);
    }

    /**
     * Parse Accept-Language header to get primary language code.
     */
    private function parseAcceptLanguage(string $header): string
    {
        $parts = explode(',', $header);
        $first = trim($parts[0]);
        $lang = substr($first, 0, 2);

        return strtolower($lang);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingResource;
use App\Models\MarketingUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliateResourceController extends Controller
{
    /**
     * GET /api/marketing/resources
     *
     * Returns resources filtered by the user's affiliate role,
     * translated in the user's preferred language (fallback: en → fr).
     *
     * Request attributes (set by ResolveAffiliateRole middleware):
     *   - firebase_uid
     *   - affiliate_role (chatter|captain|influencer|blogger|group_admin|partner)
     *   - user_language  (fr|en|es|pt|ar|de|zh|ru|hi)
     */
    public function index(Request $request): JsonResponse
    {
        $role = $request->input('affiliate_role');
        $lang = $request->input('user_language', 'en');

        $query = MarketingResource::query()
            ->active()
            ->forRole($role)
            ->ordered();

        // Optional category filter
        if ($category = $request->query('category')) {
            $query->forCategory($category);
        }

        // Optional type filter
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Optional search
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search, $lang) {
                $q->whereRaw("name->>? ILIKE ?", [$lang, "%{$search}%"])
                  ->orWhereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("name->>'fr' ILIKE ?", ["%{$search}%"]);
            });
        }

        $resources = $query->get()->map(fn (MarketingResource $r) => $r->toTranslated($lang));

        return response()->json([
            'success'   => true,
            'resources' => $resources,
            'role'      => $role,
            'language'  => $lang,
        ]);
    }

    /**
     * POST /api/marketing/resources/{id}/download
     *
     * Track download and return the file URL.
     */
    public function download(Request $request, string $id): JsonResponse
    {
        $role = $request->input('affiliate_role');
        $lang = $request->input('user_language', 'en');
        $uid  = $request->input('firebase_uid');

        $resource = MarketingResource::query()
            ->active()
            ->forRole($role)
            ->findOrFail($id);

        if (!$resource->file_path) {
            return response()->json([
                'error' => 'This resource has no downloadable file.',
            ], 404);
        }

        // Atomic increment + usage log
        DB::transaction(function () use ($resource, $uid, $role, $lang) {
            $resource->increment('download_count');

            MarketingUsageLog::create([
                'resource_id' => $resource->id,
                'user_uid'    => $uid,
                'user_role'   => $role,
                'action'      => 'download',
                'language'    => $lang,
            ]);
        });

        return response()->json([
            'success'  => true,
            'file_url' => $resource->resolveUrl($resource->file_path),
            'file_format' => $resource->file_format,
            'file_size'   => $resource->file_size,
        ]);
    }

    /**
     * POST /api/marketing/resources/{id}/copy
     *
     * Track copy action and return the translated content.
     * Replaces placeholders if applicable (group_admin, blogger articles).
     */
    public function copy(Request $request, string $id): JsonResponse
    {
        $role = $request->input('affiliate_role');
        $lang = $request->input('user_language', 'en');
        $uid  = $request->input('firebase_uid');

        $resource = MarketingResource::query()
            ->active()
            ->forRole($role)
            ->findOrFail($id);

        $content = $resource->translate('content', $lang);

        if (!$content) {
            return response()->json([
                'error' => 'This resource has no copyable content.',
            ], 404);
        }

        // Replace placeholders — only allow declared placeholders on the resource
        $replacements = $request->input('replacements', []);
        $allowedPlaceholders = $resource->placeholders ?? [];
        if (is_array($replacements) && count($replacements) > 0 && count($allowedPlaceholders) > 0) {
            foreach ($replacements as $placeholder => $value) {
                if (in_array($placeholder, $allowedPlaceholders, true)) {
                    $content = str_replace($placeholder, strip_tags((string) $value), $content);
                }
            }
        }

        // Atomic increment + usage log
        DB::transaction(function () use ($resource, $uid, $role, $lang) {
            $resource->increment('copy_count');

            MarketingUsageLog::create([
                'resource_id' => $resource->id,
                'user_uid'    => $uid,
                'user_role'   => $role,
                'action'      => 'copy',
                'language'    => $lang,
            ]);
        });

        return response()->json([
            'success' => true,
            'content' => $content,
            'name'    => $resource->translate('name', $lang),
        ]);
    }

    /**
     * GET /api/marketing/resources/{id}/processed
     *
     * Get resource content with ALL placeholders replaced.
     * Used by GroupAdmin (posts) and Blogger (articles with {{AFFILIATE_LINK}}).
     */
    public function processed(Request $request, string $id): JsonResponse
    {
        $role = $request->input('affiliate_role');
        $lang = $request->input('user_language', 'en');
        $uid  = $request->input('firebase_uid');

        $resource = MarketingResource::query()
            ->active()
            ->forRole($role)
            ->findOrFail($id);

        $content = $resource->translate('content', $lang);

        if (!$content) {
            return response()->json([
                'error' => 'This resource has no content.',
            ], 404);
        }

        // Auto-resolve placeholders from user data
        $userReplacements = $this->getUserPlaceholders($uid, $role);
        foreach ($userReplacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        // Track view
        MarketingUsageLog::create([
            'resource_id' => $resource->id,
            'user_uid'    => $uid,
            'user_role'   => $role,
            'action'      => 'view',
            'language'    => $lang,
        ]);

        return response()->json([
            'success'      => true,
            'content'      => $content,
            'name'         => $resource->translate('name', $lang),
            'placeholders' => $resource->placeholders,
        ]);
    }

    /**
     * Resolve user-specific placeholder values from Firestore.
     */
    private function getUserPlaceholders(string $uid, string $role): array
    {
        try {
            $firestore = \Kreait\Laravel\Firebase\Facades\Firebase::firestore()->database();

            // Map role to Firestore collection
            $collection = match ($role) {
                'chatter', 'captain' => 'chatters',
                'influencer'         => 'influencers',
                'blogger'            => 'bloggers',
                'group_admin'        => 'group_admins',
                'partner'            => 'partners',
                default              => null,
            };

            if (!$collection) {
                return [];
            }

            $doc = $firestore->collection($collection)->document($uid)->snapshot();

            if (!$doc->exists()) {
                return [];
            }

            $data = $doc->data();
            $affiliateCode = $data['affiliateCodeClient'] ?? $data['affiliateCode'] ?? '';
            $recruitmentCode = $data['affiliateCodeRecruitment'] ?? '';
            $firstName = $data['firstName'] ?? $data['first_name'] ?? '';
            $name = ($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? '');

            return [
                '{{AFFILIATE_LINK}}'    => "https://sos-expat.com/?ref={$affiliateCode}",
                '{{RECRUITMENT_LINK}}'  => "https://sos-expat.com/chatter/register?ref={$recruitmentCode}",
                '{{PROVIDER_LINK}}'     => "https://sos-expat.com/register-provider?ref={$affiliateCode}",
                '{{GROUP_NAME}}'        => $data['groupName'] ?? '',
                '{{ADMIN_NAME}}'        => trim($name),
                '{{ADMIN_FIRST_NAME}}'  => $firstName,
                '{{DISCOUNT_AMOUNT}}'   => $data['clientDiscountAmount'] ?? '',
                '{{DISCOUNT_PERCENT}}'  => $data['clientDiscountPercent'] ?? '',
                '{{AFFILIATE_CODE}}'    => $affiliateCode,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('getUserPlaceholders failed', [
                'uid'   => $uid,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

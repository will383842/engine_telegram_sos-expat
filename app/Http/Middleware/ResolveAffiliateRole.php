<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that:
 * 1. Verifies Firebase Auth ID token
 * 2. Resolves ALL active affiliate roles from Firestore
 * 3. Resolves the user's preferred language
 * 4. Picks the active role (from ?as_role= param, or first found)
 *
 * Merges into request: firebase_uid, affiliate_role, affiliate_roles[], user_language
 *
 * A user can have multiple roles (e.g., chatter + influencer).
 * The endpoint uses affiliate_role (singular) for filtering.
 * The user can override with ?as_role=influencer if they have that role.
 */
class ResolveAffiliateRole
{
    /**
     * Firestore collections to check for affiliate role.
     * Order defines priority when no ?as_role= param is given.
     */
    private const ROLE_COLLECTIONS = [
        'chatters'     => 'chatter',
        'influencers'  => 'influencer',
        'bloggers'     => 'blogger',
        'group_admins' => 'group_admin',
        'partners'     => 'partner',
    ];

    private const SUPPORTED_LANGUAGES = ['fr', 'en', 'es', 'pt', 'ar', 'de', 'zh', 'ru', 'hi'];

    public function handle(Request $request, Closure $next): Response
    {
        // ── Step 1: Verify Firebase token ──
        $authHeader = $request->header('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized. Missing or malformed Authorization header.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $idToken = substr($authHeader, 7);

        try {
            $auth = Firebase::auth();
            $verifiedToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');

            if (empty($uid)) {
                return response()->json([
                    'error' => 'Unauthorized. Could not extract uid from token.',
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (\Throwable $e) {
            Log::warning('ResolveAffiliateRole: Token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unauthorized. Invalid Firebase ID token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // ── Step 2: Resolve ALL roles & language (cached 5 min) ──
        $cacheKey = "affiliate_roles:{$uid}";

        $userData = Cache::remember($cacheKey, 300, function () use ($uid) {
            return $this->resolveFromFirestore($uid);
        });

        if (!$userData || empty($userData['roles'])) {
            return response()->json([
                'error' => 'Forbidden. No active affiliate role found.',
            ], Response::HTTP_FORBIDDEN);
        }

        // ── Step 3: Pick active role ──
        $requestedRole = $request->query('as_role');
        $allRoles = $userData['roles'];

        if ($requestedRole && in_array($requestedRole, $allRoles)) {
            $activeRole = $requestedRole;
        } else {
            // Default: first role found (by priority order)
            $activeRole = $allRoles[0];
        }

        $request->merge([
            'firebase_uid'    => $uid,
            'affiliate_role'  => $activeRole,
            'affiliate_roles' => $allRoles,
            'user_language'   => $userData['language'],
        ]);

        return $next($request);
    }

    /**
     * Check ALL Firestore collections to find which affiliate roles the user has.
     * Returns all active roles + preferred language.
     */
    private function resolveFromFirestore(string $uid): ?array
    {
        try {
            $firestore = Firebase::firestore()->database();
            $roles = [];
            $language = 'en';

            foreach (self::ROLE_COLLECTIONS as $collection => $role) {
                $doc = $firestore->collection($collection)->document($uid)->snapshot();

                if (!$doc->exists()) {
                    continue;
                }

                $data = $doc->data();

                // Skip inactive accounts
                $status = $data['status'] ?? 'active';
                if ($status !== 'active') {
                    continue;
                }

                // Detect captain chatter (sub-role of chatter)
                if ($role === 'chatter') {
                    $isCaptain = $data['isCaptain'] ?? false;
                    $captainTier = $data['captainTier'] ?? null;

                    if ($isCaptain || $captainTier) {
                        // Captain gets BOTH captain and chatter resources
                        $roles[] = 'captain';
                    }
                    $roles[] = 'chatter';
                } else {
                    $roles[] = $role;
                }

                // Use language from first found role
                if (count($roles) <= 2) { // first role (captain counts as 2)
                    $lang = $data['language'] ?? 'en';
                    $language = in_array($lang, self::SUPPORTED_LANGUAGES) ? $lang : 'en';
                }
            }

            if (empty($roles)) {
                return null;
            }

            return [
                'roles'    => $roles,
                'language' => $language,
            ];
        } catch (\Throwable $e) {
            Log::error('ResolveAffiliateRole: Firestore lookup failed', [
                'uid'   => $uid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

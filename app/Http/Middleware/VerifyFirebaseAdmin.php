<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyFirebaseAdmin
{
    /**
     * Validate Firebase Auth ID token AND check that the user has admin role.
     *
     * Delegates token verification to VerifyFirebaseToken logic, then adds
     * the admin-role gate.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized. Missing or malformed Authorization header.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $idToken = substr($authHeader, 7);

        try {
            $auth          = Firebase::auth();
            $verifiedToken = $auth->verifyIdToken($idToken);
            $uid           = $verifiedToken->claims()->get('sub');

            if (empty($uid)) {
                return response()->json([
                    'error' => 'Unauthorized. Could not extract uid from token.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Check admin role from Firestore
            $isAdmin = false;

            try {
                $firestore = Firebase::firestore()->database();
                $userDoc   = $firestore->collection('users')->document($uid)->snapshot();

                if ($userDoc->exists()) {
                    $data    = $userDoc->data();
                    $isAdmin = ($data['role'] ?? '') === 'admin';
                }
            } catch (\Throwable $e) {
                Log::warning('VerifyFirebaseAdmin: Could not check admin role', [
                    'uid'   => $uid,
                    'error' => $e->getMessage(),
                ]);
            }

            if (!$isAdmin) {
                return response()->json([
                    'error' => 'Forbidden. Admin role required.',
                ], Response::HTTP_FORBIDDEN);
            }

            $request->merge([
                'firebase_uid' => $uid,
                'is_admin'     => true,
            ]);

            return $next($request);
        } catch (\Throwable $e) {
            Log::warning('VerifyFirebaseAdmin: Token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unauthorized. Invalid Firebase ID token.',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}

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
     * Reads the `role` custom claim directly from the JWT token.
     * Custom claims are set by Firebase Functions (syncRoleClaims.ts)
     * via admin.auth().setCustomUserClaims(uid, { role }).
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

            // Read role from JWT custom claims (set by Firebase syncRoleClaims trigger)
            $role    = $verifiedToken->claims()->get('role', '');
            $isAdmin = $role === 'admin';

            if (!$isAdmin) {
                Log::info('VerifyFirebaseAdmin: Access denied — not admin', [
                    'uid'  => $uid,
                    'role' => $role ?: 'none',
                ]);

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

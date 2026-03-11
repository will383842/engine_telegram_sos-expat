<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyFirebaseToken
{
    /**
     * Validate Firebase Auth ID token from the Authorization Bearer header.
     *
     * Reads the `role` custom claim directly from the JWT token.
     * On success, merges `firebase_uid` and `is_admin` into the request.
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
            $auth = Firebase::auth();
            $verifiedToken = $auth->verifyIdToken($idToken);

            $uid = $verifiedToken->claims()->get('sub');

            if (empty($uid)) {
                return response()->json([
                    'error' => 'Unauthorized. Could not extract uid from token.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Read role from JWT custom claims (set by Firebase syncRoleClaims trigger)
            $role    = $verifiedToken->claims()->get('role', '');
            $isAdmin = $role === 'admin';

            $request->merge([
                'firebase_uid' => $uid,
                'is_admin'     => $isAdmin,
            ]);

            return $next($request);
        } catch (\Throwable $e) {
            Log::warning('VerifyFirebaseToken: Token verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unauthorized. Invalid Firebase ID token.',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}

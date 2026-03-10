<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFirebaseWebhook
{
    /**
     * Validate X-Engine-Secret header against the configured API secret.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('engine.api_secret');

        if (empty($expected)) {
            return response()->json([
                'error' => 'Server misconfiguration: API secret not set.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $provided = $request->header('X-Engine-Secret');

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'Unauthorized. Invalid or missing X-Engine-Secret header.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

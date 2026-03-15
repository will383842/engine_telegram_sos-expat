<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS Middleware — Allows cross-origin requests from the SOS-Expat frontend.
 *
 * Required because the frontend (https://sos-expat.com) calls the
 * Laravel engine API (https://engine-telegram-sos-expat.life-expat.com).
 */
class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = array_filter([
            config('engine.frontend_url'),
            'https://sos-expat.com',
            'https://www.sos-expat.com',
        ]);

        $origin = $request->header('Origin');

        // Only set CORS headers if origin is allowed
        if (! $origin || ! in_array($origin, $allowedOrigins, true)) {
            // No CORS headers for non-browser or unknown origins
            if ($request->isMethod('OPTIONS')) {
                return response('', 204);
            }

            return $next($request);
        }

        // Handle preflight (OPTIONS)
        if ($request->isMethod('OPTIONS')) {
            return response('', 204, [
                'Access-Control-Allow-Origin'  => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Accept, X-Requested-With',
                'Access-Control-Max-Age'       => '86400',
            ]);
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With');

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    /**
     * Validate Telegram webhook by checking the X-Telegram-Bot-Api-Secret-Token header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('telegram.webhook_secret');

        if (empty($expected)) {
            return response()->json([
                'error' => 'Server misconfiguration: Telegram webhook secret not set.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'error' => 'Unauthorized. Invalid or missing Telegram secret token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

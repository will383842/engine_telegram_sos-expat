<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware — runs on ALL requests (including preflight OPTIONS)
        $middleware->prepend(\App\Http\Middleware\CorsMiddleware::class);

        $middleware->alias([
            'firebase.webhook'   => \App\Http\Middleware\VerifyFirebaseWebhook::class,
            'firebase.token'     => \App\Http\Middleware\VerifyFirebaseToken::class,
            'firebase.admin'     => \App\Http\Middleware\VerifyFirebaseAdmin::class,
            'firebase.affiliate' => \App\Http\Middleware\ResolveAffiliateRole::class,
            'telegram.verify'    => \App\Http\Middleware\VerifyTelegramWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

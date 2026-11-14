<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS before forwarding HTTP to Apache.
        // Only trust all proxy addresses when deployed behind Render's edge.
        if (filter_var($_ENV['RENDER'] ?? $_SERVER['RENDER'] ?? getenv('RENDER'), FILTER_VALIDATE_BOOLEAN)) {
            $middleware->trustProxies(
                at: '*',
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_PORT,
            );
        }

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();

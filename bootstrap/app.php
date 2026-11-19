<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->trimStrings(except: [
            '*.current_password',
            '*.password',
            '*.password_confirmation',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            function (Request $request): bool {
                $apiPrefix = trim((string) config('app.api_prefix', 'api/v1'), '/');

                return $request->is($apiPrefix, $apiPrefix.'/*') || $request->expectsJson();
            },
        );
    })->create();

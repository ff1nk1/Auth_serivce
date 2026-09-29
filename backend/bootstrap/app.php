<?php

use App\Http\Middleware\AddTokenFromCookie;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Kafka\MainConsumer;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',

        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: [
            'login',
            'refresh',
            'profile',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'cookie.token' => AddTokenFromCookie::class,
        ]);

        /*
     * jwt.refresh должен выполняться ДО auth:api.
     * Иначе Laravel сортирует auth выше по приоритету,
     * и при протухшем токене наш middleware вообще не запустится.
     */
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            AuthenticationException $e,
            Request $request
        ) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();

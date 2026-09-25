<?php

use App\Http\Middleware\JwtRefreshMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        //web: __DIR__.'/../routes/web.php',

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
            'jwt.refresh' => JwtRefreshMiddleware::class,
            'cookie.token' => \App\Http\Middleware\AddTokenFromCookie::class,
        ]);


        /*
     * jwt.refresh должен выполняться ДО auth:api.
     * Иначе Laravel сортирует auth выше по приоритету,
     * и при протухшем токене наш middleware вообще не запустится.
     */
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            JwtRefreshMiddleware::class,

        );
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

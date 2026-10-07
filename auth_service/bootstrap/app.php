<?php

use App\Http\Middleware\AddTokenFromCookie;
use App\Http\Middleware\EnsureValidAccessToken;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->preventRequestForgery(except: [
            'api/*',
        ]);

        $middleware->encryptCookies(except: [
            'access_token',
            'refresh_token',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'cookie.token' => AddTokenFromCookie::class,
            'ensure.token' => EnsureValidAccessToken::class,
        ]);

        // Authenticate is sorted via AuthenticatesRequests contract — without this,
        // auth:api runs before ensure.token and silent refresh never executes.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: EnsureValidAccessToken::class,
        );
        $middleware->prependToPriorityList(
            before: EnsureValidAccessToken::class,
            prepend: AddTokenFromCookie::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (
            AuthenticationException $e,
            Request $request
        ) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();

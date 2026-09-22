<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use App\Http\Middleware\HandleInertiaRequests;

use PHPOpenSourceSaver\JWTAuth\Http\Middleware\Authenticate as JwtAuthenticate;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\CheckBlacklist as JwtCheckBlacklist;
use PHPOpenSourceSaver\JWTAuth\Http\Middleware\RefreshToken as JwtRefreshToken;




return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: [ // убрать в проде
            'login',
        ]);
        $middleware->alias([
        'role' => RoleMiddleware::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'jwt.auth'      => JwtAuthenticate::class,
            'jwt.refresh'   => JwtRefreshToken::class,
        ]);
    })

    
    ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (
        AuthenticationException $e,
        Request $request
    ) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }
    });
    })->create();
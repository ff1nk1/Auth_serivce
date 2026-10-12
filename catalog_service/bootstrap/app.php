<?php

use App\Http\Middleware\AuthenticateFromGatewayHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->preventRequestForgery(except: [
            'api/*',
            // Livewire upload-file is authenticated via relative signed URL.
            'admin-catalog/livewire/upload-file',
        ]);

        $middleware->redirectGuestsTo(fn () => null);

        // Ensure gateway headers are applied before Filament Authenticate.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AuthenticateFromGatewayHeaders::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('admin-catalog', 'admin-catalog/*')) {
                return response('Unauthenticated.', 401);
            }
        });
    })->create();

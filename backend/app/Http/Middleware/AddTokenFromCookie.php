<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddTokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        // Если кука с токеном есть, а заголовка Authorization еще нет
        if ($request->hasCookie('access_token') && !$request->bearerToken()) {
            // Перекладываем токен в заголовок, чтобы auth:api его увидел
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('access_token'));
        }

        return $next($request);
    }
}
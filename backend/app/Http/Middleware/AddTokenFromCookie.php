<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddTokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasCookie('access_token') && ! $request->bearerToken()) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie('access_token'));
        }

        return $next($request);
    }
}

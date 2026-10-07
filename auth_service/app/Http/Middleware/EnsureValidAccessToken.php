<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If access_token is missing/expired but refresh_token is valid, silently refresh
 * and attach new cookies to the outgoing response.
 */
class EnsureValidAccessToken
{
    public function __construct(
        private AuthService $authService,
        private JwtService $jwtService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->jwtService->userFromRequest($request);

        if ($user) {
            return $next($request);
        }

        $refreshToken = $request->cookie('refresh_token');
        if (! $refreshToken) {
            return $next($request);
        }

        $tokens = $this->authService->refreshTokens($refreshToken);
        if (! $tokens) {
            return $next($request);
        }

        $request->cookies->set('access_token', $tokens['access_token']);
        $request->cookies->set('refresh_token', $tokens['refresh_token']);
        $request->headers->set('Authorization', 'Bearer '.$tokens['access_token']);

        /** @var Response $response */
        $response = $next($request);

        return $this->attachTokenCookies($response, $tokens['access_token'], $tokens['refresh_token']);
    }

    private function attachTokenCookies(Response $response, string $accessToken, string $refreshToken): Response
    {
        $refreshTtl = (int) config('jwt.refresh_ttl');
        $cookieMinutes = (int) ceil($refreshTtl / 60);
        $secure = app()->environment('production');
        $domain = config('jwt.cookie_domain');

        $response->headers->setCookie(cookie(
            'access_token',
            $accessToken,
            $cookieMinutes,
            '/',
            $domain,
            $secure,
            true,
            false,
            'lax'
        ));

        $response->headers->setCookie(cookie(
            'refresh_token',
            $refreshToken,
            $cookieMinutes,
            '/',
            $domain,
            $secure,
            true,
            false,
            'lax'
        ));

        return $response;
    }
}

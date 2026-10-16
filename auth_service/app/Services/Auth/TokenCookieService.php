<?php

namespace App\Services\Auth;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TokenCookieService
{
    /**
     * Attach access + refresh HttpOnly cookies with correct TTLs.
     */
    public function attach(Response $response, string $accessToken, string $refreshToken): Response
    {
        $secure = (bool) config('jwt.cookie_secure');
        $domain = config('jwt.cookie_domain');

        $response->headers->setCookie(cookie(
            'access_token',
            $accessToken,
            $this->accessCookieMinutes(),
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
            $this->refreshCookieMinutes(),
            '/',
            $domain,
            $secure,
            true,
            false,
            'lax'
        ));

        return $response;
    }

    /**
     * Expire auth cookies on the response.
     */
    public function clear(Response $response): Response
    {
        $secure = (bool) config('jwt.cookie_secure');
        $domain = config('jwt.cookie_domain');

        $response->headers->setCookie(cookie(
            'access_token',
            '',
            -2628000,
            '/',
            $domain,
            $secure,
            true,
            false,
            'lax'
        ));

        $response->headers->setCookie(cookie(
            'refresh_token',
            '',
            -2628000,
            '/',
            $domain,
            $secure,
            true,
            false,
            'lax'
        ));

        return $response;
    }

    /**
     * JSON success body with token cookies attached.
     *
     * @param  array<string, mixed>  $payload
     */
    public function jsonWithTokens(
        string $accessToken,
        string $refreshToken,
        array $payload = ['message' => 'Успешно.'],
    ): JsonResponse {
        /** @var JsonResponse $response */
        $response = response()->json($payload);

        $this->attach($response, $accessToken, $refreshToken);

        return $response;
    }

    private function accessCookieMinutes(): int
    {
        return max(1, (int) config('jwt.ttl', 15));
    }

    private function refreshCookieMinutes(): int
    {
        return max(1, (int) ceil((int) config('jwt.refresh_ttl') / 60));
    }
}

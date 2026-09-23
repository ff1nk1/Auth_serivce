<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtRefreshMiddleware
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $accessToken = $request->cookie('access_token');
        $refreshToken = $request->cookie('refresh_token');

        /*
         * Access cookie отсутствует, но refresh есть —
         * пытаемся восстановить access.
         */
        if (! $accessToken && $refreshToken) {
            return $this->refreshAndContinue(
                $request,
                $next,
                $refreshToken
            );
        }

        /*
         * Access есть — проверяем, валиден ли он.
         */
        if ($accessToken) {
            try {
                $payload = $this->jwtService->decode($accessToken);

                /*
                 * Access token валиден — пропускаем дальше.
                 */
                return $next($request);

            } catch (Throwable $e) {

                /*
                 * Токен невалиден (истёк, повреждён, не та подпись —
                 * всё равно нужно обновить). Пробуем refresh.
                 */
                if ($refreshToken) {

                    return $this->refreshAndContinue(
                        $request,
                        $next,
                        $refreshToken
                    );
                }
            }
        }

        /*
         * Ни access, ни refresh — пусть auth:api вернёт 401/302.
         */
        return $next($request);
    }

    private function refreshAndContinue(
        Request $request,
        Closure $next,
        string $oldRefreshToken
    ): Response {

        $oldHash = hash('sha256', $oldRefreshToken);
        $oldKey = "refresh_token:{$oldHash}";
        $blacklistKey = "refresh_token:blacklist:{$oldHash}";

        /*
         * Refresh token уже использован (reuse detection).
         */
        if (Redis::exists($blacklistKey)) {
            Log::warning('JWT REFRESH: refresh token is blacklisted');

            return $next($request);
        }

        /*
         * Ищем refresh token в Redis.
         */
        $userId = Redis::get($oldKey);

        if (! $userId) {

            return $next($request);
        }

        $user = User::find($userId);

        if (! $user) {

            Redis::del($oldKey);

            return $next($request);
        }

        /*
         * TTL старого refresh token — чтобы blacklist жил столько же.
         */
        $oldTtl = Redis::ttl($oldKey);

        if ($oldTtl > 0) {
            Redis::setex(
                $blacklistKey,
                $oldTtl,
                '1'
            );
        }

        /*
         * Удаляем старый активный refresh.
         */
        Redis::del($oldKey);

        /*
         * Новый access JWT.
         */
        $newAccessToken = $this->jwtService
            ->createAccessToken($user);

        /*
         * Новый refresh token.
         */
        $newRefreshToken = bin2hex(
            random_bytes(64)
        );

        $newHash = hash('sha256', $newRefreshToken);

        $refreshTtl = (int) config('jwt.refresh_ttl');

        Redis::setex(
            "refresh_token:{$newHash}",
            $refreshTtl,
            $user->id
        );

        $request->cookies->set(
            'access_token',
            $newAccessToken
        );

        $request->cookies->set(
            'refresh_token',
            $newRefreshToken
        );

        /*
         * И сбрасываем кэш guard'ов, если кто-то до нас уже
         * дёрнул auth()->user() (например, HandleInertiaRequests).
         * Иначе auth:api вернёт закешированный null.
         */
        Auth::forgetGuards();

        /*
         * Пропускаем запрос дальше — теперь уже с новым токеном.
         */
        $response = $next($request);

        /*
         * Ставим новые cookies в очередь.
         *
         * Cookie::queue() работает с ЛЮБЫМ ответом, включая
         * Inertia\Response, у которого нет метода ->cookie().
         */
        $cookieAccessMinutes = (int) ceil($refreshTtl / 60);
        $cookieRefreshMinutes = (int) ceil($refreshTtl / 60);
        $secure = app()->environment('production');

        Cookie::queue(
            'access_token',
            $newAccessToken,
            $cookieAccessMinutes,
            '/',
            null,
            $secure,
            true,   // httpOnly
            false,  // raw
            'lax'   // sameSite
        );

        Cookie::queue(
            'refresh_token',
            $newRefreshToken,
            $cookieRefreshMinutes,
            '/',
            null,
            $secure,
            true,
            false,
            'lax'
        );

        return $response;
    }
}

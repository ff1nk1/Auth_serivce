<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Throwable;

class JwtRefreshMiddleware
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $accessToken = $request->cookie('access_token');
        $refreshToken = $request->cookie('refresh_token');
        \Log::info('JWT REFRESH START', [
        'has_access' => !empty($accessToken),
        'has_refresh' => !empty($refreshToken),
        ]);
    
        /*
         * Если access cookie отсутствует, но refresh есть,
         * тоже пытаемся восстановить access.
         */
        if (!$accessToken && $refreshToken) {
            return $this->refreshAndContinue(
                $request,
                $next,
                $refreshToken
            );
        }

        /*
         * Если access есть — проверяем, истёк ли он.
         */
        if ($accessToken) {
            try {
                $this->jwtService->decode($accessToken);

                /*
                 * Access token валиден.
                 * Ничего делать не нужно.
                 */
                return $next($request);

            } catch (ExpiredException) {
                /*
                 * Access token именно истёк.
                 */
                
                if ($refreshToken) {
                    \Log::info('JWT REFRESH: trying refresh');
                    return $this->refreshAndContinue(
                        $request,
                        $next,
                        $refreshToken
                    );
                }

            } catch (Throwable) {
                /*
                 * Повреждённый JWT, неправильная подпись
                 * и т.п. — refresh автоматически не делаем.
                 */
            }
        }

        /*
         * Пусть auth:api уже вернёт 401.
         */
        return $next($request);
    }

    private function refreshAndContinue(
        Request $request,
        Closure $next,
        string $oldRefreshToken
    ): Response {
        $oldHash = hash(
            'sha256',
            $oldRefreshToken
        );

        $oldKey = "refresh_token:{$oldHash}";
        $blacklistKey = "refresh_token:blacklist:{$oldHash}";

        /*
         * Refresh token уже был использован.
         */
        if (Redis::exists($blacklistKey)) {
            return $next($request);
        }

        /*
         * Ищем refresh token в Redis.
         */
        $userId = Redis::get($oldKey);

        if (!$userId) {
            return $next($request);
        }

        $user = User::find($userId);

        if (!$user) {
            Redis::del($oldKey);

            return $next($request);
        }

        /*
         * TTL старого refresh token.
         */
        $oldTtl = Redis::ttl($oldKey);

        /*
         * Старый refresh token -> blacklist.
         */
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
         * Создаём новый access token.
         */
        $newAccessToken = $this->jwtService
            ->createAccessToken($user);

        /*
         * Создаём новый refresh token.
         */
        $newRefreshToken = bin2hex(
            random_bytes(64)
        );

        $newHash = hash(
            'sha256',
            $newRefreshToken
        );

        $refreshTtl = (int) config(
            'jwt.refresh_ttl'
        );

        Redis::setex(
            "refresh_token:{$newHash}",
            $refreshTtl,
            $user->id
        );

        /*
         * КРИТИЧЕСКИЙ МОМЕНТ:
         *
         * auth:api будет выполняться ПОСЛЕ этого middleware.
         *
         * Поэтому надо изменить cookie прямо
         * в текущем Request, чтобы auth:api увидел
         * уже новый access token.
         */
        $request->cookies->set(
            'access_token',
            $newAccessToken
        );

        $request->cookies->set(
            'refresh_token',
            $newRefreshToken
        );

        /*
         * Передаём запрос дальше.
         */
        $response = $next($request);

        /*
         * А теперь обновляем реальные cookies
         * в браузере.
         */
        $accessMinutes = (int) config(
            'jwt.ttl',
            15
        );

        $refreshMinutes = (int) ceil(
            $refreshTtl / 60
        );

        $secure = app()->environment(
            'production'
        );

        $response->cookie(
            'access_token',
            $newAccessToken,
            $accessMinutes,
            '/',
            null,
            $secure,
            true,
            false,
            'lax'
        );

        $response->cookie(
            'refresh_token',
            $newRefreshToken,
            $refreshMinutes,
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
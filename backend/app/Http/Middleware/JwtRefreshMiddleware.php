<?php

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtRefreshMiddleware
{
    public function __construct(
        private JwtService $jwtService,
        private AuthService $authService
    ) {}

    public function handle(Request $request, Closure $next): Response 
    {
        $accessToken = $request->cookie('access_token');
        $refreshToken = $request->cookie('refresh_token');

        // Access отсутствует, но refresh есть — пытаемся восстановить
        if (! $accessToken && $refreshToken) {
            return $this->refreshAndContinue($request, $next, $refreshToken);
        }

        // Access есть — проверяем, валиден ли он
        if ($accessToken) {
            try {
                $this->jwtService->decode($accessToken);
                
                // Токен валиден — пропускаем дальше
                return $next($request);
            } catch (Throwable $e) {
                // Токен невалиден (истёк). Пробуем refresh.
                if ($refreshToken) {
                    return $this->refreshAndContinue($request, $next, $refreshToken);
                }
            }
        }

        // Ни access, ни refresh — пропускаем, пусть auth:api разбирается
        return $next($request);
    }

    private function refreshAndContinue(
        Request $request,
        Closure $next,
        string $oldRefreshToken
    ): Response {
        
        // ВСЯ ЛОГИКА ТЕПЕРЬ В ОДНОМ ВЫЗОВЕ СЕРВИСА!
        $tokens = $this->authService->refreshTokens($oldRefreshToken);

        // Если токен невалиден, в блэклисте или юзер удалён
        if (! $tokens) {
            Log::warning('JWT REFRESH: failed to refresh token via middleware');
            return $next($request);
        }

        // 1. Подменяем куки в текущем Request, чтобы контроллеры видели новые токены
        $request->cookies->set('access_token', $tokens['access_token']);
        $request->cookies->set('refresh_token', $tokens['refresh_token']);

        // 2. Сбрасываем кэш guard'ов
        Auth::forgetGuards();

        // 3. Передаём запрос дальше по цепочке
        $response = $next($request);

        // 4. Вешаем новые куки на Response, который улетит в браузер
        $refreshTtl = (int) config('jwt.refresh_ttl');
        $cookieMinutes = (int) ceil($refreshTtl / 60);
        $secure = app()->environment('production');

        Cookie::queue('access_token', $tokens['access_token'], $cookieMinutes, '/', null, $secure, true, false, 'lax');
        Cookie::queue('refresh_token', $tokens['refresh_token'], $cookieMinutes, '/', null, $secure, true, false, 'lax');

        return $response;
    }
}
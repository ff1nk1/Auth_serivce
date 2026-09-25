<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AuthService $authService // Инжектим наш новый сервис вместо JwtService
    ) {}


    public function registration(RegistrationRequest $request)
    {
        $user = $this->authService->register($request->validated());

        return response()->json($user, 201);
    }

    public function get_user(Request $request) 
    {
    // Берем текущего пользователя прямо из объекта запроса
        $user = $request->user();
    

    return response()->json($user);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $tokens = $this->authService->login($credentials);

        return $this->tokenResponse($tokens['access_token'], $tokens['refresh_token']);
    }

    public function refresh(Request $request)
    {
        $oldRefreshToken = $request->cookie('refresh_token');
        Log::debug('олд рефреш - ', ['old_refresh'=> $oldRefreshToken]);
        $tokens = $this->authService->refreshTokens($oldRefreshToken);

        if (! $tokens) {
            return response()->json([
                'message' => 'Недействительный refresh-токен или пользователь не найден.',
            ], 401);
        }

        return $this->tokenResponse($tokens['access_token'], $tokens['refresh_token']);
    }

    public function logout(Request $request)
    {
        $this->authService->logout(
            $request->cookie('access_token'),
            $request->cookie('refresh_token')
        );

        return response()->json(['message' => 'Выход выполнен.'])
            ->withoutCookie('access_token')
            ->withoutCookie('refresh_token');
    }

    public function profile()
    {
        return response()->json(['user' => Auth::guard('api')->user()]);
    }

    public function editData(Request $request)
    {
        $user = $request->user();


        $validated = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'number' => 'sometimes|string|max:20',
        ]);

        $user->update($validated); 

        return response()->json([
            'message' => 'Данные успешно обновлены',
            'user'    => $user->fresh()
        ]);
    }

    /**
     * Формирование ответа с HttpOnly куками
     */
    private function tokenResponse(string $accessToken, string $refreshToken)
    {
        $refreshTtl = (int) config('jwt.refresh_ttl');
        $cookieMinutes = (int) ceil($refreshTtl / 60);
        $secure = app()->environment('production');

        return response()->json(['message' => 'Успешно.'])
            ->cookie('access_token', $accessToken, $cookieMinutes, '/', null, $secure, true, false, 'lax')
            ->cookie('refresh_token', $refreshToken, $cookieMinutes, '/', null, $secure, true, false, 'lax');
    }
}
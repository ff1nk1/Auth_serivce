<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
//./vendor/bin/pint --test
//./vendor/bin/pint
class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    public function registration_page()
    {
        return Inertia::render('Registration');
    }

    public function login_page()
    {
        return Inertia::render('Login');
    }

    public function registration(RegistrationRequest $request)
    {
        $userData = $request->validated();

        $userData['password'] = Hash::make($userData['password']);

        $roleId = Role::where('slug', 'customer')
            ->valueOrFail('id');

        $userData['role_id'] = $roleId;

        $user = User::create($userData);

        return response()->json($user, 201);
    }

    /**
     * Вход.
     *
     * Access и refresh токены не возвращаем в JSON.
     * Они устанавливаются как HttpOnly cookies.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        if (
            ! $user ||
            ! Hash::check(
                $credentials['password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'email' => ['Неверный логин или пароль.'],
            ]);
        }

        /*
         * Создаём access JWT.
         */
        $accessToken = $this->jwtService
            ->createAccessToken($user);

        /*
         * Создаём refresh token.
         */
        $refreshToken = bin2hex(
            random_bytes(64)
        );

        /*
         * В Redis сохраняем только hash refresh token.
         */
        $refreshHash = hash(
            'sha256',
            $refreshToken
        );

        $refreshTtl = (int) config(
            'jwt.refresh_ttl'
        );

        Redis::setex(
            "refresh_token:{$refreshHash}",
            $refreshTtl,
            $user->id
        );

        return $this->tokenResponse(
            $accessToken,
            $refreshToken
        );
    }

    /**
     * Обновление access + refresh токенов.
     *
     * Refresh token берём из HttpOnly cookie.
     */
    public function refresh(Request $request)
    {
        $oldRefreshToken = $request->cookie(
            'refresh_token'
        );

        if (! $oldRefreshToken) {
            return response()->json([
                'message' => 'Недействительный refresh-токен.',
            ], 401);
        }

        $oldHash = hash(
            'sha256',
            $oldRefreshToken
        );

        $oldKey = "refresh_token:{$oldHash}";

        $blacklistKey =
            "refresh_token:blacklist:{$oldHash}";

        /*
         * Refresh token уже был использован
         * или отозван.
         */
        if (Redis::exists($blacklistKey)) {
            return response()->json([
                'message' => 'Недействительный refresh-токен.',
            ], 401);
        }

        /*
         * Получаем user_id из Redis.
         */
        $userId = Redis::get($oldKey);

        if (! $userId) {
            return response()->json([
                'message' => 'Недействительный refresh-токен.',
            ], 401);
        }

        /*
         * Получаем пользователя.
         */
        $user = User::find($userId);

        if (! $user) {
            Redis::del($oldKey);

            return response()->json([
                'message' => 'Пользователь не найден.',
            ], 401);
        }

        /*
         * Получаем оставшийся TTL старого refresh token.
         */
        $oldTtl = Redis::ttl($oldKey);

        /*
         * Старый refresh token отправляем в blacklist.
         */
        if ($oldTtl > 0) {
            Redis::setex(
                $blacklistKey,
                $oldTtl,
                '1'
            );
        }

        /*
         * Удаляем старый активный refresh token.
         */
        Redis::del($oldKey);

        /*
         * Новый access token.
         */
        $accessToken = $this->jwtService
            ->createAccessToken($user);

        /*
         * Новый refresh token.
         */
        $refreshToken = bin2hex(
            random_bytes(64)
        );

        $newHash = hash(
            'sha256',
            $refreshToken
        );

        $refreshTtl = (int) config(
            'jwt.refresh_ttl'
        );

        Redis::setex(
            "refresh_token:{$newHash}",
            $refreshTtl,
            $user->id
        );

        return $this->tokenResponse(
            $accessToken,
            $refreshToken
        );
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        /*
         * Access token теперь берём из cookie.
         *
         * JwtService также поддерживает Bearer fallback,
         * поэтому при необходимости старые API-клиенты
         * продолжат работать.
         */
        $accessToken = $request->cookie(
            'access_token'
        );

        if ($accessToken) {
            $this->jwtService
                ->revokeToken($accessToken);
        }

        /*
         * Refresh token берём из cookie.
         */
        $refreshToken = $request->cookie(
            'refresh_token'
        );

        if ($refreshToken) {
            $hash = hash(
                'sha256',
                $refreshToken
            );

            $key = "refresh_token:{$hash}";

            $blacklistKey =
                "refresh_token:blacklist:{$hash}";

            $ttl = Redis::ttl($key);

            if ($ttl > 0) {
                Redis::setex(
                    $blacklistKey,
                    $ttl,
                    '1'
                );
            }

            Redis::del($key);
        }

        /*
         * Удаляем cookies из браузера.
         */
        return response()->json([
            'message' => 'Выход выполнен.',
        ])
            ->withoutCookie('access_token')
            ->withoutCookie('refresh_token');
    }

    /**
     * Профиль текущего пользователя.
     *
     * Должен вызываться после auth:api.
     */
    public function profile()
    {
        $user = Auth::guard('api')->user();

        return Inertia::render('Profile', [
            'user' => $user,
        ]);
    }

    private function tokenResponse(
        string $accessToken,
        string $refreshToken
    ) {
        $refreshTtl = (int) config('jwt.refresh_ttl');

        $cookieMinutes = (int) ceil($refreshTtl / 60);

        $secure = app()->environment('production');

        return response()->json([
            'message' => 'Успешно.',
        ])
            ->cookie(
                'access_token',
                $accessToken,
                $cookieMinutes,
                '/',
                null,
                $secure,
                true,
                false,
                'lax'
            )
            ->cookie(
                'refresh_token',
                $refreshToken,
                $cookieMinutes,
                '/',
                null,
                $secure,
                true,
                false,
                'lax'
            );
    }
}

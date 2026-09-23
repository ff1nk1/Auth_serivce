<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    public function registration_page()
    {
        return Inertia::render('Registration');
    }

    public function registration(RegistrationRequest $request)
    {
        $user_data = $request->validated();

        $user_data['password'] = Hash::make($user_data['password']);

        $roleId = Role::where('slug', 'customer')->valueOrFail('id');

        $user_data['role_id'] = $roleId;

        $user = User::create($user_data);

        return response()->json($user, 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Неверный логин или пароль.'],
            ]);
        }

        $access_token = $this->jwtService->createAccessToken($user);

        $refresh_token = bin2hex(random_bytes(64));

        $tokenHash = hash('sha256', $refresh_token);

        $refreshTtl = (int) config('jwt.refresh_ttl');

        Redis::setex(
            "refresh_token:{$tokenHash}",
            $refreshTtl,
            $user->id
        );

        return response()->json([
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'refresh_expires_in' => $refreshTtl,
        ]);
    }

    public function logout(Request $request)
{
    // Инвалидируем access token
    $accessToken = $request->bearerToken();

    if ($accessToken) {
        $this->jwtService->revokeToken($accessToken);
    }

    // Инвалидируем refresh token
    if ($request->filled('refresh_token')) {
        $refreshToken = $request->string('refresh_token')->toString();

        $hash = hash('sha256', $refreshToken);

        $key = "refresh_token:{$hash}";
        $blacklistKey = "refresh_token:blacklist:{$hash}";

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

    return response()->json([
        'message' => 'Выход выполнен',
    ]);
}
    public function refresh(Request $request)
{
    $request->validate([
        'refresh_token' => ['required', 'string'],
    ]);

    $oldRefreshToken = $request->string('refresh_token')->toString();
    $oldHash = hash('sha256', $oldRefreshToken);

    $oldKey = "refresh_token:{$oldHash}";
    $blacklistKey = "refresh_token:blacklist:{$oldHash}";

    // Refresh token уже использовался или был отозван
    if (Redis::exists($blacklistKey)) {
        throw ValidationException::withMessages([
            'refresh_token' => ['Недействительный refresh-токен.'],
        ]);
    }

    $userId = Redis::get($oldKey);

    if (!$userId) {
        throw ValidationException::withMessages([
            'refresh_token' => ['Недействительный refresh-токен.'],
        ]);
    }

    $user = User::find($userId);

    if (!$user) {
        Redis::del($oldKey);

        throw ValidationException::withMessages([
            'refresh_token' => ['Пользователь не найден.'],
        ]);
    }

    // Сохраняем оставшийся TTL старого refresh token
    $oldTtl = Redis::ttl($oldKey);

    // Переносим старый refresh token в blacklist
    if ($oldTtl > 0) {
        Redis::setex(
            $blacklistKey,
            $oldTtl,
            '1'
        );
    }

    // Удаляем старый активный refresh token
    Redis::del($oldKey);

    // Новый access token
    $accessToken = $this->jwtService->createAccessToken($user);

    // Новый refresh token
    $refreshToken = bin2hex(random_bytes(64));
    $newHash = hash('sha256', $refreshToken);

    $refreshTtl = (int) config('jwt.refresh_ttl');

    Redis::setex(
        "refresh_token:{$newHash}",
        $refreshTtl,
        $user->id
    );

    return response()->json([
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type' => 'bearer',
        'expires_in' => (int) config('jwt.ttl') * 60,
        'refresh_expires_in' => $refreshTtl,
    ]);
}
    public function me()
    {
        return response()->json(
            Auth::guard('api')->user()
        );
    }
}
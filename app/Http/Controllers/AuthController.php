<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redis;

use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    $access_token = Auth::guard('api')->attempt($credentials);
    if (!$access_token) {
        throw ValidationException::withMessages([
            'email' => ['Неверный логин или пароль.'],
        ]);
    }

    $refresh_token = bin2hex(random_bytes(64));
    $tokenHash = hash('sha256', $refresh_token);
    $user = Auth::guard('api')->user();

    $exp = (int) config('jwt.refresh_ttl');
    Redis::setex(
        "refresh_token:{$tokenHash}",
        $exp,
        $user->id
    );

    return response()->json([
        'access_token'  => $access_token,
        'refresh_token' => $refresh_token,
        'token_type'    => 'bearer',
        'expires_in' => (int) config('jwt.ttl') * 60,
    ]);
}
    public function logout(Request $request)
{
    // 1. Инвалидируем access-токен — пакет сам положит его в blacklist (в Redis через cache)
    Auth::guard('api')->logout();

    // 2. Удаляем refresh-токен из Redis, если клиент его прислал
    if ($request->filled('refresh_token')) {
        $hash = hash('sha256', $request->refresh_token);
        Redis::del("refresh_token:{$hash}");
    }

    return response()->json(['message' => 'Выход выполнен']);
}


public function refresh(Request $request)
{   
    $old_refresh_token = request()->refresh_token;
    $old_hash = hash('sha256', $old_refresh_token);
    $oldKey = "refresh_token:{$old_hash}";

    $userId = Redis::get($oldKey);

    if (! $userId) {
        throw ValidationException::withMessages([
            'refresh_token' => ['Недействительный refresh-токен.'],
        ]);
    }

    $user = User::find($userId);
    if (! $user) {
        Redis::del($oldKey);
        throw ValidationException::withMessages([
            'refresh_token' => ['Пользователь не найден.'],
        ]);
    }

    // 3. РОТАЦИЯ: удаляем старый refresh
    Redis::del($oldKey);

    // 4. Выпускаем новый access
    $access_token = Auth::guard('api')->login($user);

    // 5. Выпускаем новый refresh и кладём в Redis
    $refresh_token = bin2hex(random_bytes(64));
    $newHash = hash('sha256', $refresh_token);
    $refreshTtl = (int) config('jwt.refresh_ttl');
    Redis::setex("refresh_token:{$newHash}", $refreshTtl, $user->id);

    return response()->json([
        'access_token'       => $access_token,
        'refresh_token'      => $refresh_token,
        'token_type'         => 'bearer',
        'expires_in' => (int) config('jwt.ttl') * 60,
        'refresh_expires_in' => $refreshTtl,
    ]);

}
    public function me()
    {
        return response()->json(Auth::user());
    }
}
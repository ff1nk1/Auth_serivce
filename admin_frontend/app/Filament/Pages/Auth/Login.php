<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\AuthApiClient;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var AuthApiClient $api */
        $api = app(AuthApiClient::class);

        try {
            $profile = $api->login($data['email'], $data['password']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'data.email' => $e->errors()['email'] ?? ['Invalid credentials.'],
            ]);
        }

        $roleSlug = $profile['role']['slug'] ?? $profile['role_slug'] ?? null;
        if (! in_array($roleSlug, ['admin', 'analyst'], true)) {
            $api->logout();
            throw ValidationException::withMessages([
                'data.email' => ['Only administrators and analysts can access this panel.'],
            ]);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $profile['email']],
            [
                'name' => $profile['name'] ?? $profile['email'],
                'password' => Hash::make(Str::random(40)),
                'auth_user_id' => $profile['id'],
                'role_slug' => $roleSlug,
            ]
        );

        // Preserve API JWT pair across session ID rotation (regenerate can drop them).
        $accessToken = session('auth_access_token');
        $refreshToken = session('auth_refresh_token');

        // Never use Filament "remember me": storefront shares app.localhost and a
        // long-lived remember cookie would reopen the panel after a customer login.
        Auth::guard(Filament::getAuthGuard())->login($user, false);
        session()->regenerate();

        session([
            'auth_access_token' => $accessToken,
            'auth_refresh_token' => $refreshToken,
        ]);

        return app(LoginResponse::class);
    }

    protected function getRememberFormComponent(): Component
    {
        return Hidden::make('remember')->default(false);
    }
}

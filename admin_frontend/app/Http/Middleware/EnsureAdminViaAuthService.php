<?php

namespace App\Http\Middleware;

use App\Services\AuthApiClient;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-check admin role against auth_service on every panel request.
 * Prevents Filament "remember me" / stale local mirror from keeping a non-admin in the panel
 * when browser JWT cookies belong to another user (e.g. storefront customer on same host).
 */
class EnsureAdminViaAuthService
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $profile = app(AuthApiClient::class)->user();
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (ValidationException $e) {
            $this->logoutLocal();

            throw new AuthenticationException(
                'Unauthenticated.',
                [Filament::getAuthGuard()],
                Filament::getLoginUrl(),
            );
        } catch (\Throwable) {
            $this->logoutLocal();

            throw new AuthenticationException(
                'Unauthenticated.',
                [Filament::getAuthGuard()],
                Filament::getLoginUrl(),
            );
        }

        $slug = $profile['role']['slug'] ?? $profile['role_slug'] ?? null;
        if ($slug !== 'admin') {
            try {
                app(AuthApiClient::class)->logout();
            } catch (\Throwable) {
                // ignore
            }
            $this->logoutLocal();

            throw new AuthenticationException(
                'Unauthenticated.',
                [Filament::getAuthGuard()],
                Filament::getLoginUrl(),
            );
        }

        $user = Auth::guard(Filament::getAuthGuard())->user();
        if ($user && $user->role_slug !== 'admin') {
            $user->forceFill(['role_slug' => 'admin'])->save();
        }

        return $next($request);
    }

    private function logoutLocal(): void
    {
        $guard = Filament::getCurrentPanel()?->getAuthGuard() ?? Filament::getAuthGuard();
        Auth::guard($guard)->logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}

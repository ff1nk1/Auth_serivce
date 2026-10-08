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
 * Re-check admin/analyst role against auth_service on every panel request.
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
        if (! in_array($slug, ['admin', 'analyst'], true)) {
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
        if ($user && $user->role_slug !== $slug) {
            $user->forceFill(['role_slug' => $slug])->save();
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

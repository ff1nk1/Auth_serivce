<?php

namespace App\Http\Controllers;

use App\Services\Auth\AuthService;
use App\Services\Gateway\ProxyService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GatewayController extends Controller
{
    public function __construct(
        private ProxyService $proxy,
        private AuthService $authService,
    ) {}

    public function catalog(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            $this->proxy->joinPath((string) config('services.catalog.paths.storefront'), $path),
        );
    }

    public function catalogAdmin(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            $this->proxy->joinPath((string) config('services.catalog.paths.admin_api'), $path),
        );
    }

    public function catalogAdminPanel(Request $request, string $path = ''): Response
    {
        $user = $request->user();

        $response = $this->proxy->forwardHtml(
            $request,
            (string) config('services.catalog.url'),
            $this->proxy->joinPath((string) config('services.catalog.paths.admin_panel'), $path),
            [
                'id' => (int) $user->id,
                'role' => (string) ($user->role?->slug ?? ''),
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ]
        );

        // Filament POST /admin-catalog/logout — revoke JWT so the panel cannot be re-entered
        // with the same cookies after the catalog clears its session.
        $normalizedPath = rtrim(ltrim((string) $path, '/'), '/');
        $isFilamentLogout = strtoupper($request->method()) === 'POST'
            && (
                $request->is('admin-catalog/logout')
                || $normalizedPath === 'logout'
                || str_ends_with($normalizedPath, '/logout')
            )
            && $response->getStatusCode() < 400;

        if ($isFilamentLogout) {
            $accessToken = $request->bearerToken() ?: $request->cookie('access_token');
            $refreshToken = $request->cookie('refresh_token');

            $this->authService->logout(
                is_string($accessToken) && $accessToken !== '' ? $accessToken : null,
                is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
            );

            $domain = config('jwt.cookie_domain');

            return $response
                ->withoutCookie('access_token', '/', $domain)
                ->withoutCookie('refresh_token', '/', $domain);
        }

        return $response;
    }

    public function uploadUrl(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            (string) config('services.catalog.paths.upload_url'),
        );
    }

    public function notifications(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.notification.url'),
            $this->proxy->joinPath((string) config('services.notification.paths.index'), $path),
        );
    }

    public function orders(Request $request, string $path = ''): Response
    {
        $user = $request->user();

        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            $this->proxy->joinPath((string) config('services.order.paths.customer'), $path),
            [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]
        );
    }

    public function ordersAdmin(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            $this->proxy->joinPath((string) config('services.order.paths.admin'), $path),
        );
    }

    public function stripeWebhook(Request $request): Response
    {
        return $this->proxy->forwardRaw(
            $request,
            (string) config('services.order.url'),
            (string) config('services.order.paths.stripe_webhook'),
        );
    }

    public function analytics(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.analytic.url'),
            $this->proxy->joinPath((string) config('services.analytic.paths.index'), $path),
        );
    }

    public function search(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.search.url'),
            (string) config('services.search.paths.search'),
        );
    }

    public function searchAutocomplete(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.search.url'),
            (string) config('services.search.paths.autocomplete'),
        );
    }
}

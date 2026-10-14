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
            '/api/catalog/'.ltrim($path, '/')
        );
    }

    public function catalogAdmin(Request $request, string $path = ''): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.catalog.url'),
            '/api/admin/'.ltrim($path, '/')
        );
    }

    public function catalogAdminPanel(Request $request, string $path = ''): Response
    {
        $user = $request->user();
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');

        $response = $this->proxy->forwardHtml(
            $request,
            (string) config('services.catalog.url'),
            '/admin-catalog'.$suffix,
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
            '/api/get-upload-url'
        );
    }

    public function notifications(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');

        return $this->proxy->forward(
            $request,
            (string) config('services.notification.url'),
            '/api/notifications'.$suffix
        );
    }

    public function orders(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');
        $user = $request->user();

        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            '/api/orders'.$suffix,
            [
                'id' => (int) $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]
        );
    }

    public function ordersAdmin(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');

        return $this->proxy->forward(
            $request,
            (string) config('services.order.url'),
            '/api/admin/orders'.$suffix
        );
    }

    public function stripeWebhook(Request $request): Response
    {
        return $this->proxy->forwardRaw(
            $request,
            (string) config('services.order.url'),
            '/api/webhooks/stripe'
        );
    }

    public function analytics(Request $request, string $path = ''): Response
    {
        $suffix = $path === '' ? '' : '/'.ltrim($path, '/');

        return $this->proxy->forward(
            $request,
            (string) config('services.analytic.url'),
            '/analytics'.$suffix
        );
    }

    public function search(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.search.url'),
            '/api/search'
        );
    }

    public function searchAutocomplete(Request $request): Response
    {
        return $this->proxy->forward(
            $request,
            (string) config('services.search.url'),
            '/api/search/autocomplete'
        );
    }
}


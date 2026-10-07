<?php

namespace App\Services;

use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthApiClient
{
    public function login(string $email, string $password): array
    {
        $response = Http::asJson()
            ->acceptJson()
            ->post($this->baseUrl().'/login', [
                'email' => $email,
                'password' => $password,
            ]);

        if ($response->status() === 422) {
            throw ValidationException::withMessages(
                $response->json('errors') ?? ['email' => [$response->json('message') ?? 'Login failed']]
            );
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'email' => [$response->json('message') ?? 'Invalid credentials'],
            ]);
        }

        $this->storeTokensFromResponse($response);

        if (! $this->accessToken() || ! $this->refreshToken()) {
            throw ValidationException::withMessages([
                'email' => ['Login succeeded but auth tokens were not stored. Check JWT cookie settings.'],
            ]);
        }

        return $this->user();
    }

    public function user(): array
    {
        $response = $this->send('get', '/user');

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'email' => ['Unable to fetch user profile from auth service.'],
            ]);
        }

        return $response->json();
    }

    public function getUsers(?string $email = null, int $page = 1, int $perPage = 15): array
    {
        $query = ['page' => $page, 'per_page' => $perPage];
        if ($email) {
            $query['email'] = $email;
        }

        return $this->send('get', '/admin/users', $query)->throw()->json();
    }

    public function getRoles(): array
    {
        return $this->send('get', '/admin/roles')->throw()->json();
    }

    public function changeUserRole(int $userId, int $roleId): array
    {
        return $this->send('patch', "/admin/users/{$userId}/role", ['role_id' => $roleId])
            ->throw()
            ->json();
    }

    public function getCategories(int $page = 1, int $perPage = 15): array
    {
        return $this->send('get', '/admin/categories', [
            'page' => $page,
            'per_page' => $perPage,
        ])->throw()->json();
    }

    public function createCategory(array $data): array
    {
        return $this->send('post', '/admin/categories', $data)->throw()->json();
    }

    public function updateCategory(int $id, array $data): array
    {
        return $this->send('put', "/admin/categories/{$id}", $data)->throw()->json();
    }

    public function deleteCategory(int $id): void
    {
        $this->send('delete', "/admin/categories/{$id}")->throw();
    }

    public function getProducts(int $page = 1, int $perPage = 15, ?int $storeId = null): array
    {
        $query = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($storeId !== null) {
            $query['store_id'] = $storeId;
        }

        return $this->send('get', '/admin/products', $query)->throw()->json();
    }

    public function createProduct(array $data): array
    {
        return $this->send('post', '/admin/products', $data)->throw()->json();
    }

    public function updateProduct(int $id, array $data): array
    {
        return $this->send('put', "/admin/products/{$id}", $data)->throw()->json();
    }

    public function deleteProduct(int $id): void
    {
        $this->send('delete', "/admin/products/{$id}")->throw();
    }

    public function getStores(int $page = 1, int $perPage = 100): array
    {
        return $this->send('get', '/admin/stores', [
            'page' => $page,
            'per_page' => $perPage,
        ])->throw()->json();
    }

    /**
     * @return array<int|string, string>
     */
    public function storeOptions(): array
    {
        $payload = $this->getStores(1, 100);
        $rows = $payload['data'] ?? [];

        return collect($rows)
            ->mapWithKeys(fn ($row) => [
                (int) ($row['id'] ?? 0) => (string) ($row['name'] ?? ('Store #'.($row['id'] ?? ''))),
            ])
            ->filter(fn ($label, $id) => $id > 0)
            ->all();
    }

    public function getStocks(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $query = array_filter([
            'page' => $page,
            'per_page' => $perPage,
            'store_id' => $filters['store_id'] ?? null,
            'product_id' => $filters['product_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return $this->send('get', '/admin/stocks', $query)->throw()->json();
    }

    public function createStock(array $data): array
    {
        return $this->send('post', '/admin/stocks', $data)->throw()->json();
    }

    public function updateStock(int $id, array $data): array
    {
        return $this->send('patch', "/admin/stocks/{$id}", $data)->throw()->json();
    }

    public function uploadProductImage(string $filename, string $contentType, string $contents): string
    {
        $meta = $this->send('post', '/get-upload-url', [
            'filename' => $filename,
            'content_type' => $contentType,
        ])->throw()->json();

        $uploadUrl = $meta['upload_url'] ?? null;
        $publicUrl = $meta['public_url'] ?? null;

        if (! is_string($uploadUrl) || $uploadUrl === '' || ! is_string($publicUrl) || $publicUrl === '') {
            throw ValidationException::withMessages([
                'image' => ['Upload URL was not returned by catalog service.'],
            ]);
        }

        $put = Http::withBody($contents, $contentType)
            ->withHeaders(['Content-Type' => $contentType])
            ->put($uploadUrl);

        if (! $put->successful()) {
            throw ValidationException::withMessages([
                'image' => ['Failed to upload image to storage.'],
            ]);
        }

        return $publicUrl;
    }

    public function logout(): void
    {
        try {
            $this->send('post', '/logout', [], retry: false);
        } catch (\Throwable) {
            // ignore
        }

        session()->forget(['auth_access_token', 'auth_refresh_token']);
    }

    private function send(string $method, string $uri, array $data = [], bool $retry = true): Response
    {
        $this->hydrateSessionFromRequestCookies();

        $pending = $this->pendingRequest();

        $response = match (strtolower($method)) {
            'get' => $pending->get($uri, $data),
            'post' => $pending->post($uri, $data),
            'put' => $pending->put($uri, $data),
            'patch' => $pending->patch($uri, $data),
            'delete' => $pending->delete($uri, $data),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        $this->storeTokensFromResponse($response);

        if ($response->status() === 401 && $uri !== '/refresh') {
            if ($retry && $this->refreshTokens()) {
                return $this->send($method, $uri, $data, false);
            }

            $this->forceReLogin();
        }

        return $response;
    }

    private function refreshTokens(): bool
    {
        $refresh = $this->refreshToken();
        if (! $refresh) {
            return false;
        }

        $response = Http::asJson()
            ->acceptJson()
            ->withCookies([
                'refresh_token' => $refresh,
                'access_token' => (string) ($this->accessToken() ?? ''),
            ], $this->cookieDomain())
            ->post($this->baseUrl().'/refresh');

        $this->storeTokensFromResponse($response);

        return $response->successful() && (bool) $this->accessToken();
    }

    private function pendingRequest(): PendingRequest
    {
        $pending = Http::asJson()->acceptJson()->baseUrl($this->baseUrl());

        $access = $this->accessToken();
        $refresh = $this->refreshToken();

        if ($access) {
            $pending = $pending->withToken($access);
        }

        if ($access || $refresh) {
            $pending = $pending->withCookies([
                'access_token' => (string) ($access ?? ''),
                'refresh_token' => (string) ($refresh ?? ''),
            ], $this->cookieDomain());
        }

        return $pending;
    }

    private function accessToken(): ?string
    {
        $token = session('auth_access_token') ?: request()->cookie('access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function refreshToken(): ?string
    {
        $token = session('auth_refresh_token') ?: request()->cookie('refresh_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Filament "remember me" can restore the local user without API tokens in session.
     * Browser JWT cookies (same host) are a valid fallback — sync them into session.
     */
    private function hydrateSessionFromRequestCookies(): void
    {
        if (! session('auth_access_token') && request()->cookie('access_token')) {
            session(['auth_access_token' => (string) request()->cookie('access_token')]);
        }
        if (! session('auth_refresh_token') && request()->cookie('refresh_token')) {
            session(['auth_refresh_token' => (string) request()->cookie('refresh_token')]);
        }
    }

    private function storeTokensFromResponse(Response $response): void
    {
        foreach ($response->cookies() as $cookie) {
            $name = $cookie->getName();
            if ($name === 'access_token' && $cookie->getValue() !== '') {
                session(['auth_access_token' => $cookie->getValue()]);
            }
            if ($name === 'refresh_token' && $cookie->getValue() !== '') {
                session(['auth_refresh_token' => $cookie->getValue()]);
            }
        }

        $headers = $response->headers();
        $setCookies = $headers['Set-Cookie'] ?? $headers['set-cookie'] ?? [];
        if (is_string($setCookies)) {
            $setCookies = [$setCookies];
        }

        foreach ($setCookies as $raw) {
            if (! is_string($raw)) {
                continue;
            }
            if (preg_match('/(?:^|,\s*)access_token=([^;]+)/', $raw, $m) && $m[1] !== '') {
                session(['auth_access_token' => urldecode($m[1])]);
            }
            if (preg_match('/(?:^|,\s*)refresh_token=([^;]+)/', $raw, $m) && $m[1] !== '') {
                session(['auth_refresh_token' => urldecode($m[1])]);
            }
        }
    }

    /**
     * @throws AuthenticationException
     */
    private function forceReLogin(): never
    {
        session()->forget(['auth_access_token', 'auth_refresh_token']);

        $guard = Filament::getCurrentPanel()?->getAuthGuard() ?? Filament::getAuthGuard();
        Auth::guard($guard)->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        throw new AuthenticationException(
            'Unauthenticated.',
            [$guard],
            Filament::getLoginUrl(),
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.auth.url'), '/');
    }

    private function cookieDomain(): string
    {
        $host = parse_url($this->baseUrl(), PHP_URL_HOST);

        return $host ?: 'auth_service_app';
    }
}

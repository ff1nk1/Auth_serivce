<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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

        return $this->user();
    }

    public function user(): array
    {
        $response = $this->request()->get('/user');

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

        return $this->request()->get('/admin/users', $query)->throw()->json();
    }

    public function getRoles(): array
    {
        return $this->request()->get('/admin/roles')->throw()->json();
    }

    public function changeUserRole(int $userId, int $roleId): array
    {
        return $this->request()
            ->patch("/admin/users/{$userId}/role", ['role_id' => $roleId])
            ->throw()
            ->json();
    }

    public function getCategories(int $page = 1): array
    {
        return $this->request()->get('/admin/categories', ['page' => $page])->throw()->json();
    }

    public function createCategory(array $data): array
    {
        return $this->request()->post('/admin/categories', $data)->throw()->json();
    }

    public function updateCategory(int $id, array $data): array
    {
        return $this->request()->put("/admin/categories/{$id}", $data)->throw()->json();
    }

    public function deleteCategory(int $id): void
    {
        $this->request()->delete("/admin/categories/{$id}")->throw();
    }

    public function getProducts(int $page = 1): array
    {
        return $this->request()->get('/admin/products', ['page' => $page])->throw()->json();
    }

    public function createProduct(array $data): array
    {
        return $this->request()->post('/admin/products', $data)->throw()->json();
    }

    public function updateProduct(int $id, array $data): array
    {
        return $this->request()->put("/admin/products/{$id}", $data)->throw()->json();
    }

    public function deleteProduct(int $id): void
    {
        $this->request()->delete("/admin/products/{$id}")->throw();
    }

    public function logout(): void
    {
        try {
            $this->request()->post('/logout');
        } catch (\Throwable) {
            // ignore
        }

        session()->forget(['auth_access_token', 'auth_refresh_token']);
    }

    private function request(): PendingRequest
    {
        $pending = Http::asJson()->acceptJson()->baseUrl($this->baseUrl());

        $access = session('auth_access_token');
        if ($access) {
            $pending = $pending->withToken($access)->withCookies([
                'access_token' => $access,
                'refresh_token' => (string) session('auth_refresh_token'),
            ], $this->cookieDomain());
        }

        return $pending;
    }

    private function storeTokensFromResponse(Response $response): void
    {
        foreach ($response->cookies() as $cookie) {
            $name = $cookie->getName();
            if ($name === 'access_token') {
                session(['auth_access_token' => $cookie->getValue()]);
            }
            if ($name === 'refresh_token') {
                session(['auth_refresh_token' => $cookie->getValue()]);
            }
        }

        // Fallback: parse Set-Cookie header if CookieJar is empty
        if (! session('auth_access_token')) {
            $raw = $response->header('Set-Cookie');
            if (is_string($raw) && preg_match('/access_token=([^;]+)/', $raw, $m)) {
                session(['auth_access_token' => urldecode($m[1])]);
            }
            if (is_string($raw) && preg_match('/refresh_token=([^;]+)/', $raw, $m)) {
                session(['auth_refresh_token' => urldecode($m[1])]);
            }
        }
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

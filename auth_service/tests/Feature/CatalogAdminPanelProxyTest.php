<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CatalogAdminPanelProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->select((int) config('database.redis.default.database', 15));
        Redis::flushdb();

        Role::factory()->create(['name' => 'Admin', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Customer', 'slug' => 'customer']);
    }

    public function test_admin_catalog_panel_proxy_attaches_identity_headers(): void
    {
        Http::fake([
            '*' => Http::response('<html>catalog-admin</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $user = User::factory()->create([
            'name' => 'Catalog Admin',
            'email' => 'admin-panel@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $adminRole->id,
        ]);

        $tokens = app(AuthService::class)->login([
            'email' => 'admin-panel@example.com',
            'password' => 'password123',
        ]);

        $this->withToken($tokens['access_token'])
            ->get('/admin-catalog')
            ->assertOk()
            ->assertSee('catalog-admin', false);

        Http::assertSent(function ($request) use ($user) {
            return str_contains($request->url(), '/admin-catalog')
                && $request->header('X-User-Id') === [(string) $user->id]
                && $request->header('X-User-Role') === ['admin']
                && $request->header('X-User-Name') === ['Catalog Admin']
                && $request->header('X-User-Email') === ['admin-panel@example.com']
                && $request->hasHeader('Host');
        });
    }

    public function test_catalog_admin_panel_requires_authentication(): void
    {
        $this->get('/admin-catalog')->assertUnauthorized();
    }

    public function test_catalog_admin_panel_rejects_non_admin(): void
    {
        $customerRole = Role::where('slug', 'customer')->firstOrFail();
        User::factory()->create([
            'email' => 'customer-panel@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $customerRole->id,
        ]);

        $tokens = app(AuthService::class)->login([
            'email' => 'customer-panel@example.com',
            'password' => 'password123',
        ]);

        $this->withToken($tokens['access_token'])
            ->get('/admin-catalog')
            ->assertForbidden();
    }

}

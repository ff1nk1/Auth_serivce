<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

class AuthHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->select((int) config('database.redis.default.database', 15));
        Redis::flushdb();

        Role::factory()->create([
            'name' => 'Customer',
            'slug' => 'customer',
        ]);
    }

    public function test_registration_returns_201(): void
    {
        Kafka::fake();

        $response = $this->postJson('/api/registration', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123',
            'number' => '+12345678901',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_login_sets_http_only_token_cookies(): void
    {
        $role = Role::where('slug', 'customer')->first();
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertCookie('access_token')
            ->assertCookie('refresh_token')
            ->assertJson(['message' => 'Успешно.']);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_profile_with_bearer_token(): void
    {
        $role = Role::where('slug', 'customer')->first();
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $tokens = app(AuthService::class)->login([
            'email' => 'me@example.com',
            'password' => 'password123',
        ]);

        $this->withToken($tokens['access_token'])
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_refresh_issues_new_cookies(): void
    {
        $role = Role::where('slug', 'customer')->first();
        User::factory()->create([
            'email' => 'refresh@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $tokens = app(AuthService::class)->login([
            'email' => 'refresh@example.com',
            'password' => 'password123',
        ]);

        $this->call(
            'POST',
            '/api/refresh',
            [],
            ['refresh_token' => $tokens['refresh_token']],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        )->assertOk()
            ->assertCookie('access_token')
            ->assertCookie('refresh_token');
    }

    public function test_logout_revokes_access_token(): void
    {
        $role = Role::where('slug', 'customer')->first();
        User::factory()->create([
            'email' => 'out@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
        ]);

        $tokens = app(AuthService::class)->login([
            'email' => 'out@example.com',
            'password' => 'password123',
        ]);

        $this->withToken($tokens['access_token'])
            ->withUnencryptedCookie('access_token', $tokens['access_token'])
            ->withUnencryptedCookie('refresh_token', $tokens['refresh_token'])
            ->postJson('/api/logout')
            ->assertOk();

        $jwt = app(\App\Services\Auth\JwtService::class);
        $decoded = $jwt->decode($tokens['access_token']);
        $this->assertTrue(
            Redis::exists("jwt:blacklist:{$decoded->jti}") > 0,
            'Access token JTI must be blacklisted after logout'
        );

        $request = \Illuminate\Http\Request::create('/api/user', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$tokens['access_token']);
        $this->assertNull(
            $jwt->userFromRequest($request),
            'Blacklisted access token must not resolve a user'
        );
    }
}






<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 测试环境手动创建 Passport Personal Access Client
        \Laravel\Passport\Client::create([
            'id'            => \Illuminate\Support\Str::uuid(),
            'name'          => 'Test Personal Access Client',
            'secret'        => null,
            'redirect_uris' => ['http://localhost'],
            'grant_types'   => ['personal_access'],
            'revoked'       => false,
        ]);
    }

    private function createUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'tenant_id' => 0,
            'username'  => 'testuser',
            'password'  => '123456',
            'status'    => 1,
        ], $attrs));
    }

    // -------------------------------------------------------
    // POST /api/v1/auth/login
    // -------------------------------------------------------

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure([
                'data' => ['accessToken', 'tokenType', 'expiresIn'],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('code', 400);
    }

    public function test_login_fails_with_nonexistent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'nobody',
            'password' => '123456',
        ]);

        $response->assertStatus(400);
    }

    public function test_login_fails_with_disabled_account(): void
    {
        $this->createUser(['status' => 0]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'testuser',
            'password' => '123456',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/auth/me
    // -------------------------------------------------------

    public function test_me_returns_user_profile(): void
    {
        $user = $this->createUser(['username' => 'me_user']);
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.username', 'me_user')
            ->assertJsonStructure(['data' => ['userId', 'username', 'nickname', 'roles', 'permissions']]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // GET /api/v1/auth/routes
    // -------------------------------------------------------

    public function test_routes_returns_array(): void
    {
        $user = $this->createUser();
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/auth/routes');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data']);

        $this->assertIsArray($response->json('data'));
    }

    // -------------------------------------------------------
    // POST /api/v1/auth/logout
    // -------------------------------------------------------

    public function test_logout_revokes_token(): void
    {
        $user = $this->createUser();
        Passport::actingAs($user);

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('code', 200);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    }
}

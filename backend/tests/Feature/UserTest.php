<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    // -------------------------------------------------------
    // GET /api/v1/users
    // -------------------------------------------------------

    public function test_index_returns_paginated_users(): void
    {
        User::factory()->count(3)->create(['tenant_id' => 1]);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_keywords(): void
    {
        User::factory()->create(['tenant_id' => 1, 'username' => 'unique_kw_user']);
        User::factory()->create(['tenant_id' => 1, 'username' => 'other_user']);

        $response = $this->getJson('/api/v1/users?keywords=unique_kw');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('unique_kw_user', $list[0]['username']);
    }

    public function test_index_filters_by_status(): void
    {
        User::factory()->create(['tenant_id' => 1, 'status' => 0]);
        User::factory()->create(['tenant_id' => 1, 'status' => 1]);

        $response = $this->getJson('/api/v1/users?status=0');

        $response->assertOk();
        foreach ($response->json('data.list') as $user) {
            $this->assertEquals(0, $user['status']);
        }
    }

    // -------------------------------------------------------
    // POST /api/v1/users
    // -------------------------------------------------------

    public function test_store_creates_user(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'username' => 'newuser',
            'nickname' => '新用户',
            'password' => '123456',
            'email'    => 'newuser@example.com',
            'status'   => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.username', 'newuser');

        $this->assertDatabaseHas('users', ['username' => 'newuser']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/users', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_unique_username(): void
    {
        User::factory()->create(['tenant_id' => 1, 'username' => 'dup_user']);

        $response = $this->postJson('/api/v1/users', [
            'username' => 'dup_user',
            'nickname' => '重复',
            'password' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_password_min_length(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'username' => 'shortpw',
            'nickname' => '短密码',
            'password' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/users/{id}
    // -------------------------------------------------------

    public function test_show_returns_user_detail(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);

        $response = $this->getJson("/api/v1/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.username', $user->username);
    }

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $response = $this->getJson('/api/v1/users/99999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // PUT /api/v1/users/{id}
    // -------------------------------------------------------

    public function test_update_modifies_user(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'nickname' => '新昵称',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'nickname' => '新昵称']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/users/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_user(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // -------------------------------------------------------
    // PATCH /api/v1/users/{id}/status
    // -------------------------------------------------------

    public function test_update_status_disables_user(): void
    {
        $user = User::factory()->create(['tenant_id' => 1, 'status' => 1]);

        $response = $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 0]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 0]);
    }

    public function test_update_status_validates_value(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);

        $response = $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 5]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------
    // POST /api/v1/users/{id}/reset-password
    // -------------------------------------------------------

    public function test_reset_password_changes_password(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);

        $response = $this->postJson("/api/v1/users/{$user->id}/reset-password");

        $response->assertOk()->assertJsonPath('code', 200);
    }

    // -------------------------------------------------------
    // 未认证访问
    // -------------------------------------------------------

    public function test_unauthenticated_access_is_rejected(): void
    {
        // 清除认证态
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/users');

        $response->assertUnauthorized();
    }
}

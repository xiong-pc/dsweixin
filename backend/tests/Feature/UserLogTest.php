<?php

namespace Tests\Feature;

use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createUserLog(array $attrs = []): UserLog
    {
        return UserLog::create(array_merge([
            'uid' => 1,
            'username' => 'admin',
            'site_id' => 1,
            'url' => '/api/v1/users',
            'data' => null,
            'ip' => '127.0.0.1',
            'action_name' => '创建用户',
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/system/user-logs
    // -------------------------------------------------------

    public function test_index_returns_paginated_logs(): void
    {
        $this->createUserLog();

        $response = $this->getJson('/api/v1/system/user-logs');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_keywords_username(): void
    {
        $this->createUserLog(['username' => 'admin']);
        $this->createUserLog(['username' => '张三']);

        $response = $this->getJson('/api/v1/system/user-logs?keywords=admin');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('admin', $list[0]['username']);
    }

    public function test_index_filters_by_keywords_action_name(): void
    {
        $this->createUserLog(['action_name' => '创建用户']);
        $this->createUserLog(['action_name' => '删除角色']);

        $response = $this->getJson('/api/v1/system/user-logs?keywords=创建');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('创建用户', $list[0]['action_name']);
    }

    public function test_index_filters_by_keywords_ip(): void
    {
        $this->createUserLog(['ip' => '192.168.1.1']);
        $this->createUserLog(['ip' => '10.0.0.1']);

        $response = $this->getJson('/api/v1/system/user-logs?keywords=192.168');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('192.168.1.1', $list[0]['ip']);
    }

    public function test_index_filters_by_uid(): void
    {
        $this->createUserLog(['uid' => 1]);
        $this->createUserLog(['uid' => 2]);
        $this->createUserLog(['uid' => 2]);

        $response = $this->getJson('/api/v1/system/user-logs?uid=2');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_index_filters_by_site_id(): void
    {
        $this->createUserLog(['site_id' => 1]);
        $this->createUserLog(['site_id' => 2]);

        $response = $this->getJson('/api/v1/system/user-logs?site_id=2');

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_index_filters_by_action_name(): void
    {
        $this->createUserLog(['action_name' => '创建用户']);
        $this->createUserLog(['action_name' => '删除用户']);

        $response = $this->getJson('/api/v1/system/user-logs?action_name=创建用户');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
    }

    public function test_index_pagination_works(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createUserLog(['action_name' => "操作{$i}"]);
        }

        $response = $this->getJson('/api/v1/system/user-logs?pageSize=2&pageNum=1');

        $response->assertOk();
        $this->assertEquals(5, $response->json('data.total'));
        $this->assertCount(2, $response->json('data.list'));
    }

    // -------------------------------------------------------
    // POST /api/v1/system/user-logs
    // -------------------------------------------------------

    public function test_store_creates_log(): void
    {
        $response = $this->postJson('/api/v1/system/user-logs', [
            'uid' => 1,
            'username' => 'admin',
            'site_id' => 1,
            'url' => '/api/v1/users',
            'data' => '{"name":"test"}',
            'ip' => '127.0.0.1',
            'action_name' => '新增测试',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.action_name', '新增测试');

        $this->assertDatabaseHas('user_logs', ['action_name' => '新增测试']);
    }

    public function test_store_allows_null_data_field(): void
    {
        $response = $this->postJson('/api/v1/system/user-logs', [
            'uid' => 1,
            'username' => 'admin',
            'site_id' => 1,
            'url' => '/api/v1/users',
            'ip' => '127.0.0.1',
            'action_name' => '无数据操作',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_logs', ['action_name' => '无数据操作', 'data' => null]);
    }

    // -------------------------------------------------------
    // GET /api/v1/system/user-logs/{id}
    // -------------------------------------------------------

    public function test_show_returns_log(): void
    {
        $log = $this->createUserLog(['action_name' => '查询测试']);

        $response = $this->getJson("/api/v1/system/user-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.action_name', '查询测试');
    }

    public function test_show_returns_404_for_missing_log(): void
    {
        $response = $this->getJson('/api/v1/system/user-logs/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // PUT /api/v1/system/user-logs/{id}
    // -------------------------------------------------------

    public function test_update_modifies_log(): void
    {
        $log = $this->createUserLog(['action_name' => '原始操作']);

        $response = $this->putJson("/api/v1/system/user-logs/{$log->id}", [
            'action_name' => '修改后操作',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('user_logs', ['id' => $log->id, 'action_name' => '修改后操作']);
    }

    public function test_update_returns_404_for_missing_log(): void
    {
        $response = $this->putJson('/api/v1/system/user-logs/99999', ['action_name' => 'x']);

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/system/user-logs/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_log(): void
    {
        $log = $this->createUserLog();

        $response = $this->deleteJson("/api/v1/system/user-logs/{$log->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('user_logs', ['id' => $log->id]);
    }

    public function test_destroy_returns_404_for_missing_log(): void
    {
        $response = $this->deleteJson('/api/v1/system/user-logs/99999');

        $response->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use App\Models\SysConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createConfig(array $attrs = []): SysConfig
    {
        return SysConfig::create(array_merge([
            'tenant_id' => 1,
            'name'      => '测试配置',
            'key'       => 'test_key_' . uniqid(),
            'value'     => 'test_value',
            'type'      => 0,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/configs
    // -------------------------------------------------------

    public function test_index_returns_paginated_configs(): void
    {
        $this->createConfig();

        $response = $this->getJson('/api/v1/configs');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_keywords(): void
    {
        $this->createConfig(['name' => '站点标题配置', 'key' => 'site_title_unique']);
        $this->createConfig(['name' => '其他配置']);

        $response = $this->getJson('/api/v1/configs?keywords=站点标题');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('站点标题配置', $list[0]['name']);
    }

    // -------------------------------------------------------
    // POST /api/v1/configs
    // -------------------------------------------------------

    public function test_store_creates_config(): void
    {
        $response = $this->postJson('/api/v1/configs', [
            'name'  => '新配置',
            'key'   => 'new_config_key',
            'value' => 'hello',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.key', 'new_config_key');

        $this->assertDatabaseHas('configs', ['key' => 'new_config_key']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/configs', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_unique_key(): void
    {
        $this->createConfig(['key' => 'dup_config_key']);

        $response = $this->postJson('/api/v1/configs', [
            'name' => '重复',
            'key'  => 'dup_config_key',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/configs/{id}
    // -------------------------------------------------------

    public function test_show_returns_config_detail(): void
    {
        $config = $this->createConfig(['name' => '详情配置', 'key' => 'detail_key']);

        $response = $this->getJson("/api/v1/configs/{$config->id}");

        $response->assertOk()
            ->assertJsonPath('data.key', 'detail_key');
    }

    // -------------------------------------------------------
    // PUT /api/v1/configs/{id}
    // -------------------------------------------------------

    public function test_update_modifies_config(): void
    {
        $config = $this->createConfig();

        $response = $this->putJson("/api/v1/configs/{$config->id}", [
            'value' => 'updated_value',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('configs', ['id' => $config->id, 'value' => 'updated_value']);
    }

    public function test_update_key_unique_excludes_self(): void
    {
        $config = $this->createConfig(['key' => 'my_unique_key']);

        // 用同 key 更新自己，应该通过
        $response = $this->putJson("/api/v1/configs/{$config->id}", [
            'key' => 'my_unique_key',
        ]);

        $response->assertOk();
    }

    // -------------------------------------------------------
    // DELETE /api/v1/configs/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_config(): void
    {
        $config = $this->createConfig();

        $response = $this->deleteJson("/api/v1/configs/{$config->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('configs', ['id' => $config->id]);
    }
}

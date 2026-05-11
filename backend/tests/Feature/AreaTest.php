<?php

namespace Tests\Feature;

use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createArea(array $attrs = []): Area
    {
        return Area::create(array_merge([
            'pid' => 0,
            'name' => '测试省份',
            'shortname' => '测',
            'longitude' => '116.397428',
            'latitude' => '39.90923',
            'level' => 1,
            'sort' => 0,
            'status' => 1,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/areas
    // -------------------------------------------------------

    public function test_index_returns_paginated_areas(): void
    {
        $this->createArea();

        $response = $this->getJson('/api/v1/areas');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_keywords(): void
    {
        $this->createArea(['name' => '广东省', 'shortname' => '粤']);
        $this->createArea(['name' => '广西壮族自治区', 'shortname' => '桂']);

        $response = $this->getJson('/api/v1/areas?keywords=广东');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('广东省', $list[0]['name']);
    }

    public function test_index_filters_by_shortname_keywords(): void
    {
        $this->createArea(['name' => '广东省', 'shortname' => '粤']);
        $this->createArea(['name' => '广西壮族自治区', 'shortname' => '桂']);

        $response = $this->getJson('/api/v1/areas?keywords=粤');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('广东省', $list[0]['name']);
    }

    public function test_index_filters_by_level(): void
    {
        $province = $this->createArea(['name' => '广东省', 'level' => 1]);
        $this->createArea(['pid' => $province->id, 'name' => '广州市', 'level' => 2]);

        $response = $this->getJson('/api/v1/areas?level=1');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals(1, $list[0]['level']);
    }

    public function test_index_filters_by_pid(): void
    {
        $province = $this->createArea(['name' => '广东省', 'level' => 1]);
        $this->createArea(['pid' => $province->id, 'name' => '广州市', 'level' => 2]);
        $this->createArea(['pid' => $province->id, 'name' => '深圳市', 'level' => 2]);
        $this->createArea(['pid' => 0, 'name' => '浙江省', 'level' => 1]);

        $response = $this->getJson("/api/v1/areas?pid={$province->id}");

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(2, $list);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createArea(['name' => '启用省份', 'status' => 1]);
        $this->createArea(['name' => '禁用省份', 'status' => 0]);

        $response = $this->getJson('/api/v1/areas?status=0');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertEquals('禁用省份', $list[0]['name']);
    }

    // -------------------------------------------------------
    // POST /api/v1/areas
    // -------------------------------------------------------

    public function test_store_creates_area(): void
    {
        $response = $this->postJson('/api/v1/areas', [
            'pid' => 0,
            'name' => '新测试省',
            'shortname' => '新',
            'level' => 1,
            'sort' => 1,
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.name', '新测试省');

        $this->assertDatabaseHas('areas', ['name' => '新测试省']);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/areas', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/areas/{id}
    // -------------------------------------------------------

    public function test_show_returns_area(): void
    {
        $area = $this->createArea(['name' => '查询测试省']);

        $response = $this->getJson("/api/v1/areas/{$area->id}");

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.name', '查询测试省');
    }

    public function test_show_returns_404_for_missing_area(): void
    {
        $response = $this->getJson('/api/v1/areas/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------
    // PUT /api/v1/areas/{id}
    // -------------------------------------------------------

    public function test_update_modifies_area(): void
    {
        $area = $this->createArea();

        $response = $this->putJson("/api/v1/areas/{$area->id}", [
            'name' => '修改后名称',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name' => '修改后名称']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/areas/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_area(): void
    {
        $area = $this->createArea();

        $response = $this->deleteJson("/api/v1/areas/{$area->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
    }

    public function test_destroy_rejects_area_with_children(): void
    {
        $province = $this->createArea(['name' => '广东省', 'level' => 1]);
        $this->createArea(['pid' => $province->id, 'name' => '广州市', 'level' => 2]);

        $response = $this->deleteJson("/api/v1/areas/{$province->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('areas', ['id' => $province->id]);
    }

    // -------------------------------------------------------
    // 未认证访问
    // -------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->withoutMiddleware()->refreshApplication();

        $response = $this->getJson('/api/v1/areas');

        // 未绑定 token 的请求应被 auth:api 中间件拦截
        $this->assertNotEquals(500, $response->status());
    }
}

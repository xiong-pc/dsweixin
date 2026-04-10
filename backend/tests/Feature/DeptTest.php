<?php

namespace Tests\Feature;

use App\Models\Dept;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createDept(array $attrs = []): Dept
    {
        return Dept::create(array_merge([
            'tenant_id' => 1,
            'parent_id' => 0,
            'name'      => '测试部门',
            'sort'      => 1,
            'status'    => 1,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/depts
    // -------------------------------------------------------

    public function test_index_returns_dept_tree(): void
    {
        $parent = $this->createDept(['name' => '总部']);
        $this->createDept(['parent_id' => $parent->id, 'name' => '技术部']);

        $response = $this->getJson('/api/v1/depts');

        $response->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertIsArray($response->json('data'));
    }

    public function test_index_filters_by_keywords(): void
    {
        $this->createDept(['name' => '搜索专属部门']);
        $this->createDept(['name' => '其他部门']);

        $response = $this->getJson('/api/v1/depts?keywords=搜索专属');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('搜索专属部门', $names);
    }

    // -------------------------------------------------------
    // POST /api/v1/depts
    // -------------------------------------------------------

    public function test_store_creates_dept(): void
    {
        $response = $this->postJson('/api/v1/depts', [
            'name'   => '新建部门',
            'sort'   => 5,
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.name', '新建部门');

        $this->assertDatabaseHas('depts', ['name' => '新建部门']);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/depts', ['sort' => 1]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_parent_exists(): void
    {
        $response = $this->postJson('/api/v1/depts', [
            'name'      => '子部门',
            'parent_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/depts/{id}
    // -------------------------------------------------------

    public function test_show_returns_dept_detail(): void
    {
        $dept = $this->createDept(['name' => '详情部门']);

        $response = $this->getJson("/api/v1/depts/{$dept->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', '详情部门');
    }

    // -------------------------------------------------------
    // PUT /api/v1/depts/{id}
    // -------------------------------------------------------

    public function test_update_modifies_dept(): void
    {
        $dept = $this->createDept();

        $response = $this->putJson("/api/v1/depts/{$dept->id}", [
            'name' => '修改后部门名',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('depts', ['id' => $dept->id, 'name' => '修改后部门名']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/depts/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_dept(): void
    {
        $dept = $this->createDept();

        $response = $this->deleteJson("/api/v1/depts/{$dept->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('depts', ['id' => $dept->id]);
    }

    public function test_destroy_fails_when_dept_has_children(): void
    {
        $parent = $this->createDept(['name' => '父部门']);
        $this->createDept(['parent_id' => $parent->id, 'name' => '子部门']);

        $response = $this->deleteJson("/api/v1/depts/{$parent->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('depts', ['id' => $parent->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createRole(array $attrs = []): Role
    {
        return Role::create(array_merge([
            'tenant_id' => 1,
            'name'      => '测试角色',
            'code'      => 'TEST_' . uniqid(),
            'sort'      => 1,
            'status'    => 1,
        ], $attrs));
    }

    private function createMenu(): Menu
    {
        return Menu::create([
            'parent_id'  => 0,
            'name'       => '测试菜单',
            'type'       => 2,
            'path'       => '/test',
            'component'  => 'test/index',
            'sort'       => 1,
            'visible'    => 1,
        ]);
    }

    // -------------------------------------------------------
    // GET /api/v1/roles
    // -------------------------------------------------------

    public function test_index_returns_paginated_roles(): void
    {
        $this->createRole();

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total', 'page', 'pageSize']]);
    }

    public function test_index_filters_by_keywords(): void
    {
        $this->createRole(['name' => '特殊角色名称', 'code' => 'UNIQUE_ROLE']);
        $this->createRole(['name' => '普通角色']);

        $response = $this->getJson('/api/v1/roles?keywords=特殊角色');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
    }

    // -------------------------------------------------------
    // POST /api/v1/roles
    // -------------------------------------------------------

    public function test_store_creates_role(): void
    {
        $response = $this->postJson('/api/v1/roles', [
            'name'   => '新角色',
            'code'   => 'NEW_ROLE',
            'sort'   => 5,
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.code', 'NEW_ROLE');

        $this->assertDatabaseHas('roles', ['code' => 'NEW_ROLE']);
    }

    public function test_store_creates_role_with_menus(): void
    {
        $menu = $this->createMenu();

        $response = $this->postJson('/api/v1/roles', [
            'name'    => '带菜单角色',
            'code'    => 'ROLE_WITH_MENU',
            'menuIds' => [$menu->id],
        ]);

        $response->assertOk();
        $role = Role::where('code', 'ROLE_WITH_MENU')->first();
        $this->assertTrue($role->menus->contains($menu->id));
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/roles', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_unique_code(): void
    {
        $this->createRole(['code' => 'DUP_CODE']);

        $response = $this->postJson('/api/v1/roles', [
            'name' => '重复',
            'code' => 'DUP_CODE',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/roles/{id}
    // -------------------------------------------------------

    public function test_show_returns_role_detail(): void
    {
        $role = $this->createRole(['name' => '详情角色', 'code' => 'DETAIL_ROLE']);

        $response = $this->getJson("/api/v1/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'DETAIL_ROLE');
    }

    // -------------------------------------------------------
    // PUT /api/v1/roles/{id}
    // -------------------------------------------------------

    public function test_update_modifies_role(): void
    {
        $role = $this->createRole();

        $response = $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => '修改后名称',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => '修改后名称']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/roles/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_role(): void
    {
        $role = $this->createRole();

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    // -------------------------------------------------------
    // GET /api/v1/roles/{id}/menus
    // -------------------------------------------------------

    public function test_get_role_menus_returns_menu_ids(): void
    {
        $menu = $this->createMenu();
        $role = $this->createRole();
        $role->menus()->sync([$menu->id]);

        $response = $this->getJson("/api/v1/roles/{$role->id}/menus");

        $response->assertOk();
        $this->assertContains($menu->id, $response->json('data'));
    }

    // -------------------------------------------------------
    // PUT /api/v1/roles/{id}/menus
    // -------------------------------------------------------

    public function test_update_menus_syncs_role_menus(): void
    {
        $menu1 = $this->createMenu();
        $menu2 = $this->createMenu();
        $role  = $this->createRole();
        $role->menus()->sync([$menu1->id]);

        $response = $this->putJson("/api/v1/roles/{$role->id}/menus", [
            'menuIds' => [$menu2->id],
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $role->refresh();
        $this->assertTrue($role->menus->contains($menu2->id));
        $this->assertFalse($role->menus->contains($menu1->id));
    }
}

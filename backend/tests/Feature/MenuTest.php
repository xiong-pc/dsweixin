<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
    }

    private function createMenu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'parent_id' => 0,
            'name'      => '测试菜单',
            'type'      => 2,
            'path'      => '/test_' . uniqid(),
            'component' => 'test/index',
            'sort'      => 1,
            'visible'   => 1,
        ], $attrs));
    }

    // -------------------------------------------------------
    // GET /api/v1/menus
    // -------------------------------------------------------

    public function test_index_returns_menu_tree(): void
    {
        $parent = $this->createMenu(['type' => 1, 'name' => '父目录']);
        $this->createMenu(['parent_id' => $parent->id, 'name' => '子菜单']);

        $response = $this->getJson('/api/v1/menus');

        $response->assertOk()
            ->assertJsonPath('code', 200);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_index_filters_by_keywords(): void
    {
        $this->createMenu(['name' => '专属搜索菜单']);
        $this->createMenu(['name' => '其他菜单']);

        $response = $this->getJson('/api/v1/menus?keywords=专属搜索');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('专属搜索菜单', $names);
        $this->assertNotContains('其他菜单', $names);
    }

    // -------------------------------------------------------
    // POST /api/v1/menus
    // -------------------------------------------------------

    public function test_store_creates_directory_menu(): void
    {
        $response = $this->postJson('/api/v1/menus', [
            'name'      => '新目录',
            'type'      => 1,
            'path'      => '/new',
            'component' => 'Layout',
            'sort'      => 10,
            'visible'   => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.name', '新目录');

        $this->assertDatabaseHas('menus', ['name' => '新目录', 'type' => 1]);
    }

    public function test_store_creates_button_permission(): void
    {
        $response = $this->postJson('/api/v1/menus', [
            'name'       => '新增用户按钮',
            'type'       => 3,
            'permission' => 'sys:user:add',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('menus', ['permission' => 'sys:user:add']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/menus', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_store_validates_type_enum(): void
    {
        $response = $this->postJson('/api/v1/menus', [
            'name' => '类型错误',
            'type' => 99,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    // -------------------------------------------------------
    // GET /api/v1/menus/{id}
    // -------------------------------------------------------

    public function test_show_returns_menu_detail(): void
    {
        $menu = $this->createMenu(['name' => '详情菜单']);

        $response = $this->getJson("/api/v1/menus/{$menu->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', '详情菜单');
    }

    // -------------------------------------------------------
    // PUT /api/v1/menus/{id}
    // -------------------------------------------------------

    public function test_update_modifies_menu(): void
    {
        $menu = $this->createMenu();

        $response = $this->putJson("/api/v1/menus/{$menu->id}", [
            'name' => '修改后菜单名',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'name' => '修改后菜单名']);
    }

    // -------------------------------------------------------
    // DELETE /api/v1/menus/{id}
    // -------------------------------------------------------

    public function test_destroy_deletes_menu(): void
    {
        $menu = $this->createMenu();

        $response = $this->deleteJson("/api/v1/menus/{$menu->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    public function test_destroy_fails_when_menu_has_children(): void
    {
        $parent = $this->createMenu(['type' => 1]);
        $this->createMenu(['parent_id' => $parent->id]);

        $response = $this->deleteJson("/api/v1/menus/{$parent->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('menus', ['id' => $parent->id]);
    }
}

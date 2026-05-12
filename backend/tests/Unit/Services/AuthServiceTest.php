<?php

namespace Tests\Unit\Services;

use App\Services\Api\AuthService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for AuthService::buildMenuTree (pure function).
 *
 * 不加载 Laravel app，用 stdClass 模拟 Menu 对象（buildMenuTree 仅依赖对象属性）。
 */
class AuthServiceTest extends TestCase
{
    /**
     * Helper to create a fake menu object with the properties buildMenuTree reads.
     */
    private function menu(array $attrs): stdClass
    {
        $m = new stdClass;
        $m->id = $attrs['id'] ?? 0;
        $m->parent_id = $attrs['parent_id'] ?? 0;
        $m->path = $attrs['path'] ?? '';
        $m->component = $attrs['component'] ?? '';
        $m->name = $attrs['name'] ?? '';
        $m->redirect = $attrs['redirect'] ?? null;
        $m->icon = $attrs['icon'] ?? null;
        $m->visible = $attrs['visible'] ?? true;

        return $m;
    }

    #[Test]
    public function build_menu_tree_returns_empty_array_when_no_menus(): void
    {
        $this->assertSame([], AuthService::buildMenuTree([], 0));
    }

    #[Test]
    public function build_menu_tree_builds_route_node_with_meta(): void
    {
        $menus = [
            $this->menu([
                'id' => 1,
                'parent_id' => 0,
                'path' => '/dashboard',
                'component' => 'dashboard/index',
                'name' => 'Dashboard',
                'icon' => 'house',
                'visible' => true,
            ]),
        ];

        $result = AuthService::buildMenuTree($menus, 0);

        $this->assertCount(1, $result);
        $this->assertSame('/dashboard', $result[0]['path']);
        $this->assertSame('dashboard/index', $result[0]['component']);
        $this->assertSame('Dashboard', $result[0]['name']);
        $this->assertSame('Dashboard', $result[0]['meta']['title']);
        $this->assertSame('house', $result[0]['meta']['icon']);
        $this->assertFalse($result[0]['meta']['hidden']);
    }

    #[Test]
    public function build_menu_tree_sets_meta_hidden_true_when_visible_false(): void
    {
        $menus = [
            $this->menu(['id' => 1, 'parent_id' => 0, 'name' => 'Secret', 'visible' => false]),
        ];

        $result = AuthService::buildMenuTree($menus, 0);

        $this->assertTrue($result[0]['meta']['hidden']);
    }

    #[Test]
    public function build_menu_tree_returns_null_when_redirect_empty(): void
    {
        $menus = [
            $this->menu(['id' => 1, 'parent_id' => 0, 'name' => 'NoRedirect', 'redirect' => '']),
        ];

        $result = AuthService::buildMenuTree($menus, 0);

        $this->assertNull($result[0]['redirect']);
    }

    #[Test]
    public function build_menu_tree_preserves_redirect_when_provided(): void
    {
        $menus = [
            $this->menu(['id' => 1, 'parent_id' => 0, 'name' => 'Has', 'redirect' => '/home']),
        ];

        $result = AuthService::buildMenuTree($menus, 0);

        $this->assertSame('/home', $result[0]['redirect']);
    }

    #[Test]
    public function build_menu_tree_nests_children_recursively(): void
    {
        $menus = [
            $this->menu(['id' => 1, 'parent_id' => 0, 'name' => 'System']),
            $this->menu(['id' => 2, 'parent_id' => 1, 'name' => 'Users']),
            $this->menu(['id' => 3, 'parent_id' => 2, 'name' => 'UserDetail']),
        ];

        $result = AuthService::buildMenuTree($menus, 0);

        $this->assertSame('System', $result[0]['name']);
        $this->assertArrayHasKey('children', $result[0]);
        $this->assertSame('Users', $result[0]['children'][0]['name']);
        $this->assertSame('UserDetail', $result[0]['children'][0]['children'][0]['name']);
    }

    #[Test]
    public function build_menu_tree_omits_children_key_for_leaf_node(): void
    {
        $menus = [
            $this->menu(['id' => 1, 'parent_id' => 0, 'name' => 'Leaf']),
        ];

        $result = AuthService::buildMenuTree($menus, 0);

        $this->assertArrayNotHasKey('children', $result[0]);
    }

    #[Test]
    public function build_menu_tree_filters_by_specified_parent_id(): void
    {
        $menus = [
            $this->menu(['id' => 1, 'parent_id' => 0, 'name' => 'A']),
            $this->menu(['id' => 2, 'parent_id' => 1, 'name' => 'AChild']),
            $this->menu(['id' => 3, 'parent_id' => 0, 'name' => 'B']),
        ];

        // 从 parent_id=1 起，只应有 AChild
        $result = AuthService::buildMenuTree($menus, 1);

        $this->assertCount(1, $result);
        $this->assertSame('AChild', $result[0]['name']);
    }
}

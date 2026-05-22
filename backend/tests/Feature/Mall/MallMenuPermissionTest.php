<?php

namespace Tests\Feature\Mall;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\Api\AuthService;
use Database\Seeders\MallMenuSeeder;
use Database\Seeders\MallPermissionSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mall 菜单 / 权限种子（M10-PR37）。
 *
 * 验证：
 *   - MallMenuSeeder 写入完整树（1 顶级 + 4 子目录 + 13 二级菜单）
 *   - MallPermissionSeeder 注入 33 个 mall:* 按钮权限
 *   - SUPER_ADMIN 角色（赋全部菜单）能看到商城树
 *   - 仅授系统菜单的角色看不到商城树
 *   - hasPermissionKey 对 mall:* 字符串的精确匹配
 */
class MallMenuPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureDefaultTenant();
        // 仅跑菜单相关 seeder，避免依赖 DatabaseSeeder 全量
        $this->seed(MenuSeeder::class);
        $this->seed(MallMenuSeeder::class);
        $this->seed(MallPermissionSeeder::class);
    }

    public function test_top_level_mall_catalog_seeded(): void
    {
        $mall = Menu::where('path', '/mall')->where('parent_id', 0)->first();

        $this->assertNotNull($mall);
        $this->assertSame(1, (int) $mall->type);
        $this->assertSame('商城', $mall->name);
        $this->assertSame(0, (int) $mall->tenant_id);
    }

    public function test_four_sub_catalogs_under_mall(): void
    {
        $mallId = Menu::where('path', '/mall')->value('id');
        $names = Menu::where('parent_id', $mallId)
            ->orderBy('sort')
            ->pluck('name')
            ->all();

        $this->assertSame(['商品', '订单管理', '客户', '商城设置'], $names);
    }

    public function test_product_menu_has_five_pages(): void
    {
        $productId = Menu::where('path', 'product')->where('component', 'Layout')->value('id');
        $children = Menu::where('parent_id', $productId)
            ->where('type', 2)
            ->orderBy('sort')
            ->pluck('component')
            ->all();

        $this->assertSame([
            'mall/product/index',
            'mall/category/index',
            'mall/brand/index',
            'mall/specification/index',
            'mall/attribute/index',
        ], $children);
    }

    public function test_customer_menu_has_two_pages(): void
    {
        $customerId = Menu::where('path', 'customer')->where('component', 'Layout')->value('id');
        $children = Menu::where('parent_id', $customerId)
            ->where('type', 2)
            ->orderBy('sort')
            ->pluck('name')
            ->all();

        $this->assertSame(['客户列表', '客户分组'], $children);
    }

    public function test_setting_menu_has_four_pages(): void
    {
        $settingId = Menu::where('path', 'setting')->where('component', 'Layout')->value('id');
        $components = Menu::where('parent_id', $settingId)
            ->where('type', 2)
            ->orderBy('sort')
            ->pluck('component')
            ->all();

        $this->assertSame([
            'mall/shop/index',
            'mall/payment/index',
            'mall/shipping/index',
            'mall/i18n/index',
        ], $components);
    }

    public function test_total_mall_button_permissions_count(): void
    {
        // 当前矩阵：5×3 (product) + 3 (order) + 2 (customer) + 3 (group) + 3 (shop) + 3 (payment) + 3 (shipping) + 1 (i18n)
        // = 15 + 3 + 2 + 3 + 3 + 3 + 3 + 1 = 33
        $count = Menu::where('type', 3)
            ->where('permission', 'like', 'mall:%')
            ->count();

        $this->assertSame(33, $count);
    }

    public function test_button_permissions_attached_to_correct_parent_menu(): void
    {
        $parentId = Menu::where('component', 'mall/product/index')->value('id');
        $perms = Menu::where('parent_id', $parentId)
            ->where('type', 3)
            ->orderBy('sort')
            ->pluck('permission')
            ->all();

        $this->assertSame([
            'mall:product:add',
            'mall:product:edit',
            'mall:product:delete',
        ], $perms);
    }

    public function test_order_buttons_use_action_specific_permissions(): void
    {
        $orderId = Menu::where('component', 'mall/order/index')->value('id');
        $perms = Menu::where('parent_id', $orderId)
            ->where('type', 3)
            ->orderBy('sort')
            ->pluck('permission')
            ->all();

        $this->assertSame([
            'mall:order:ship',
            'mall:order:refund',
            'mall:order:cancel',
        ], $perms);
    }

    public function test_no_duplicate_permission_keys(): void
    {
        $perms = Menu::where('type', 3)
            ->where('permission', 'like', 'mall:%')
            ->pluck('permission')
            ->all();

        $this->assertSame(count($perms), count(array_unique($perms)));
    }

    public function test_super_admin_sees_mall_in_route_tree(): void
    {
        $role = Role::create([
            'tenant_id' => 0, 'name' => '超级管理员', 'code' => 'SUPER_ADMIN',
            'data_scope' => 0, 'sort' => 1, 'status' => 1,
        ]);
        $role->menus()->sync(Menu::pluck('id'));

        $user = User::create([
            'tenant_id' => 0, 'username' => 'su', 'name' => 'su',
            'email' => 'su@example.com', 'password' => 'x', 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        $tree = app(AuthService::class)->getRouteTree($user);

        $names = array_column($tree, 'name');
        $this->assertContains('商城', $names);
    }

    public function test_role_without_mall_menus_cannot_see_mall_tree(): void
    {
        // 仅授予系统模块的菜单，不包含任何 mall 菜单
        $sysIds = Menu::where('path', '/system')
            ->orWhere(function ($q) {
                $q->where('parent_id', '!=', 0)
                    ->whereIn('parent_id', Menu::where('path', '/system')->pluck('id'));
            })
            ->pluck('id');

        $role = Role::create([
            'tenant_id' => 1, 'name' => '系统管理员', 'code' => 'SYS_ONLY',
            'data_scope' => 0, 'sort' => 1, 'status' => 1,
        ]);
        $role->menus()->sync($sysIds);

        $user = User::create([
            'tenant_id' => 1, 'username' => 'sys', 'name' => 'sys',
            'email' => 'sys@example.com', 'password' => 'x', 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        $tree = app(AuthService::class)->getRouteTree($user);
        $names = array_column($tree, 'name');

        $this->assertNotContains('商城', $names);
    }

    public function test_haspermissionkey_returns_true_when_role_has_button(): void
    {
        $role = Role::create([
            'tenant_id' => 1, 'name' => '商品操作员', 'code' => 'PRODUCT_OP',
            'data_scope' => 0, 'sort' => 1, 'status' => 1,
        ]);
        $btnId = Menu::where('permission', 'mall:product:add')->value('id');
        $role->menus()->sync([$btnId]);

        $user = User::create([
            'tenant_id' => 1, 'username' => 'po', 'name' => 'po',
            'email' => 'po@example.com', 'password' => 'x', 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermissionKey('mall:product:add'));
        $this->assertFalse($user->hasPermissionKey('mall:product:delete'));
    }

    public function test_haspermissionkey_false_for_user_without_any_mall_role(): void
    {
        $role = Role::create([
            'tenant_id' => 1, 'name' => 'NoMall', 'code' => 'NOMALL',
            'data_scope' => 0, 'sort' => 1, 'status' => 1,
        ]);
        // 不绑定任何菜单

        $user = User::create([
            'tenant_id' => 1, 'username' => 'nm', 'name' => 'nm',
            'email' => 'nm@example.com', 'password' => 'x', 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        $this->assertFalse($user->hasPermissionKey('mall:product:add'));
        $this->assertFalse($user->hasPermissionKey('mall:order:refund'));
    }

    public function test_super_admin_haspermissionkey_returns_true_for_any_mall_perm(): void
    {
        $role = Role::create([
            'tenant_id' => 0, 'name' => '超级管理员', 'code' => 'SUPER_ADMIN',
            'data_scope' => 0, 'sort' => 1, 'status' => 1,
        ]);
        // 注意：超管即使不绑定菜单，hasPermissionKey 也应返回 true（短路逻辑）
        $user = User::create([
            'tenant_id' => 0, 'username' => 'sa', 'name' => 'sa',
            'email' => 'sa@example.com', 'password' => 'x', 'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermissionKey('mall:product:add'));
        $this->assertTrue($user->hasPermissionKey('mall:order:refund'));
        $this->assertTrue($user->hasPermissionKey('any:nonexistent:perm'));
    }

    public function test_all_mall_permissions_follow_naming_convention(): void
    {
        $perms = Menu::where('type', 3)
            ->where('permission', 'like', 'mall:%')
            ->pluck('permission')
            ->all();

        foreach ($perms as $perm) {
            $this->assertMatchesRegularExpression(
                '/^mall:[a-z0-9_]+:[a-z]+$/',
                $perm,
                "权限 [$perm] 不符合 mall:resource:action 命名规范"
            );
        }
    }
}

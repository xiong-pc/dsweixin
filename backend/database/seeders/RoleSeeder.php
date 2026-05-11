<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::create([
            'tenant_id' => 0,
            'name' => '超级管理员',
            'code' => 'SUPER_ADMIN',
            'data_scope' => 0,
            'sort' => 1,
            'status' => 1,
            'remark' => '拥有所有权限',
        ]);
        $superAdmin->menus()->sync(Menu::pluck('id'));

        $admin = Role::create([
            'tenant_id' => 1,
            'name' => '系统管理员',
            'code' => 'ADMIN',
            'data_scope' => 0,
            'sort' => 2,
            'status' => 1,
            'remark' => '租户管理员',
        ]);
        $admin->menus()->sync($this->menuIdsExcludingTenantModule());

        Role::create([
            'tenant_id' => 1,
            'name' => '普通用户',
            'code' => 'USER',
            'data_scope' => 3,
            'sort' => 3,
            'status' => 1,
            'remark' => '普通用户角色',
        ]);
    }

    /**
     * 租户管理仅建议由超级管理员使用；租户管理员默认不分配，需在后台单独勾选菜单/按钮。
     *
     * @return array<int, int>
     */
    private function menuIdsExcludingTenantModule(): array
    {
        $tenantPageId = Menu::where('component', 'system/tenant/index')->value('id');
        if (!$tenantPageId) {
            return Menu::pluck('id')->all();
        }

        return Menu::query()
            ->where('id', '!=', $tenantPageId)
            ->where('parent_id', '!=', $tenantPageId)
            ->pluck('id')
            ->all();
    }
}

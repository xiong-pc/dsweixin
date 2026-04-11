<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Type: 1=Catalog, 2=Menu, 3=Button, 4=Link

        // System Management Catalog
        $system = Menu::create([
            'parent_id' => 0, 'name' => '系统管理', 'type' => 1,
            'path' => '/system', 'component' => 'Layout', 'icon' => 'Setting',
            'sort' => 1, 'visible' => 1, 'redirect' => '/system/user',
        ]);

        // User Management
        $user = Menu::create([
            'parent_id' => $system->id, 'name' => '用户管理', 'type' => 2,
            'path' => 'user', 'component' => 'system/user/index', 'icon' => 'User',
            'sort' => 1, 'visible' => 1, 'permission' => '',
        ]);
        Menu::create(['parent_id' => $user->id, 'name' => '用户新增', 'type' => 3, 'permission' => 'sys:user:add', 'sort' => 1]);
        Menu::create(['parent_id' => $user->id, 'name' => '用户编辑', 'type' => 3, 'permission' => 'sys:user:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $user->id, 'name' => '用户删除', 'type' => 3, 'permission' => 'sys:user:delete', 'sort' => 3]);

        // Role Management
        $role = Menu::create([
            'parent_id' => $system->id, 'name' => '角色管理', 'type' => 2,
            'path' => 'role', 'component' => 'system/role/index', 'icon' => 'Avatar',
            'sort' => 2, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $role->id, 'name' => '角色新增', 'type' => 3, 'permission' => 'sys:role:add', 'sort' => 1]);
        Menu::create(['parent_id' => $role->id, 'name' => '角色编辑', 'type' => 3, 'permission' => 'sys:role:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $role->id, 'name' => '角色删除', 'type' => 3, 'permission' => 'sys:role:delete', 'sort' => 3]);

        // Menu Management
        $menu = Menu::create([
            'parent_id' => $system->id, 'name' => '菜单管理', 'type' => 2,
            'path' => 'menu', 'component' => 'system/menu/index', 'icon' => 'Menu',
            'sort' => 3, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $menu->id, 'name' => '菜单新增', 'type' => 3, 'permission' => 'sys:menu:add', 'sort' => 1]);
        Menu::create(['parent_id' => $menu->id, 'name' => '菜单编辑', 'type' => 3, 'permission' => 'sys:menu:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $menu->id, 'name' => '菜单删除', 'type' => 3, 'permission' => 'sys:menu:delete', 'sort' => 3]);

        // Department Management
        $dept = Menu::create([
            'parent_id' => $system->id, 'name' => '部门管理', 'type' => 2,
            'path' => 'dept', 'component' => 'system/dept/index', 'icon' => 'OfficeBuilding',
            'sort' => 4, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $dept->id, 'name' => '部门新增', 'type' => 3, 'permission' => 'sys:dept:add', 'sort' => 1]);
        Menu::create(['parent_id' => $dept->id, 'name' => '部门编辑', 'type' => 3, 'permission' => 'sys:dept:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $dept->id, 'name' => '部门删除', 'type' => 3, 'permission' => 'sys:dept:delete', 'sort' => 3]);

        // Dictionary Management
        $dict = Menu::create([
            'parent_id' => $system->id, 'name' => '字典管理', 'type' => 2,
            'path' => 'dict', 'component' => 'system/dict/index', 'icon' => 'Collection',
            'sort' => 5, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $dict->id, 'name' => '字典新增', 'type' => 3, 'permission' => 'sys:dict:add', 'sort' => 1]);
        Menu::create(['parent_id' => $dict->id, 'name' => '字典编辑', 'type' => 3, 'permission' => 'sys:dict:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $dict->id, 'name' => '字典删除', 'type' => 3, 'permission' => 'sys:dict:delete', 'sort' => 3]);

        // System Config
        $config = Menu::create([
            'parent_id' => $system->id, 'name' => '系统配置', 'type' => 2,
            'path' => 'config', 'component' => 'system/config/index', 'icon' => 'Tools',
            'sort' => 6, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $config->id, 'name' => '配置新增', 'type' => 3, 'permission' => 'sys:config:add', 'sort' => 1]);
        Menu::create(['parent_id' => $config->id, 'name' => '配置编辑', 'type' => 3, 'permission' => 'sys:config:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $config->id, 'name' => '配置删除', 'type' => 3, 'permission' => 'sys:config:delete', 'sort' => 3]);

        // Notice Management
        $notice = Menu::create([
            'parent_id' => $system->id, 'name' => '通知公告', 'type' => 2,
            'path' => 'notice', 'component' => 'system/notice/index', 'icon' => 'Bell',
            'sort' => 7, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $notice->id, 'name' => '公告新增', 'type' => 3, 'permission' => 'sys:notice:add', 'sort' => 1]);
        Menu::create(['parent_id' => $notice->id, 'name' => '公告编辑', 'type' => 3, 'permission' => 'sys:notice:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $notice->id, 'name' => '公告删除', 'type' => 3, 'permission' => 'sys:notice:delete', 'sort' => 3]);

        // Area Management
        $area = Menu::create([
            'parent_id' => $system->id, 'name' => '地区管理', 'type' => 2,
            'path' => 'area', 'component' => 'system/area/index', 'icon' => 'Location',
            'sort' => 9, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $area->id, 'name' => '地区新增', 'type' => 3, 'permission' => 'sys:area:add', 'sort' => 1]);
        Menu::create(['parent_id' => $area->id, 'name' => '地区编辑', 'type' => 3, 'permission' => 'sys:area:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $area->id, 'name' => '地区删除', 'type' => 3, 'permission' => 'sys:area:delete', 'sort' => 3]);

        // Tenant Management
        $tenant = Menu::create([
            'parent_id' => $system->id, 'name' => '租户管理', 'type' => 2,
            'path' => 'tenant', 'component' => 'system/tenant/index', 'icon' => 'House',
            'sort' => 8, 'visible' => 1,
        ]);
        Menu::create(['parent_id' => $tenant->id, 'name' => '租户新增', 'type' => 3, 'permission' => 'sys:tenant:add', 'sort' => 1]);
        Menu::create(['parent_id' => $tenant->id, 'name' => '租户编辑', 'type' => 3, 'permission' => 'sys:tenant:edit', 'sort' => 2]);
        Menu::create(['parent_id' => $tenant->id, 'name' => '租户删除', 'type' => 3, 'permission' => 'sys:tenant:delete', 'sort' => 3]);
    }
}

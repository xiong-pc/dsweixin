<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            I18nSeeder::class,
            TenantSeeder::class,
            MenuSeeder::class,
            // M10-PR37：商城菜单 + 按钮权限（必须在 RoleSeeder 之前，
            // 否则 SUPER_ADMIN 不会自动获得新菜单）
            MallMenuSeeder::class,
            MallPermissionSeeder::class,
            RoleSeeder::class,
            DeptSeeder::class,
            UserSeeder::class,
            DictSeeder::class,
        ]);
    }
}

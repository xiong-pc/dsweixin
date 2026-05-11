<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::create([
            'tenant_id' => 0,
            'dept_id' => 0,
            'username' => 'superadmin',
            'name' => '超级管理员',
            'nickname' => '超级管理员',
            'email' => 'superadmin@example.com',
            'phone' => '13800000000',
            'password' => '123456',
            'gender' => 0,
            'status' => 1,
        ]);
        $superAdmin->roles()->attach(Role::where('code', 'SUPER_ADMIN')->first());

        $admin = User::create([
            'tenant_id' => 1,
            'dept_id' => 1,
            'username' => 'admin',
            'name' => '管理员',
            'nickname' => '管理员',
            'email' => 'admin@example.com',
            'phone' => '13800000001',
            'password' => '123456',
            'gender' => 1,
            'status' => 1,
        ]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->first());

        $user = User::create([
            'tenant_id' => 1,
            'dept_id' => 3,
            'username' => 'user',
            'name' => '普通用户',
            'nickname' => '张三',
            'email' => 'user@example.com',
            'phone' => '13800000002',
            'password' => '123456',
            'gender' => 1,
            'status' => 1,
        ]);
        $user->roles()->attach(Role::where('code', 'USER')->first());
    }
}

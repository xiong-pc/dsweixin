<?php

namespace Tests;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    /**
     * 创建超级管理员（tenant_id=0）并 actingAs
     */
    protected function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create([
            'tenant_id' => 0,
            'username'  => 'superadmin_' . uniqid(),
            'password'  => '123456',
            'status'    => 1,
        ]);

        $role = Role::firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['tenant_id' => 0, 'name' => '超级管理员', 'sort' => 1, 'status' => 1]
        );
        $user->roles()->sync([$role->id]);

        Passport::actingAs($user);

        return $user;
    }

    /**
     * 创建租户管理员（tenant_id=1）并 actingAs
     */
    protected function actingAsAdmin(): User
    {
        $tenant = Tenant::firstOrCreate(
            ['code' => 'DEFAULT'],
            ['id' => 1, 'name' => '默认租户', 'status' => 1]
        );

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'username'  => 'admin_' . uniqid(),
            'password'  => '123456',
            'status'    => 1,
        ]);

        $role = Role::firstOrCreate(
            ['code' => 'ADMIN'],
            ['tenant_id' => $tenant->id, 'name' => '管理员', 'sort' => 2, 'status' => 1]
        );
        $user->roles()->sync([$role->id]);

        Passport::actingAs($user);

        return $user;
    }

    /**
     * 创建普通用户（无角色）并 actingAs
     */
    protected function actingAsUser(int $tenantId = 1): User
    {
        Tenant::firstOrCreate(
            ['code' => 'DEFAULT'],
            ['id' => $tenantId, 'name' => '默认租户', 'status' => 1]
        );

        $user = User::factory()->create([
            'tenant_id' => $tenantId,
            'username'  => 'user_' . uniqid(),
            'password'  => bcrypt('123456'),
            'status'    => 1,
        ]);

        Passport::actingAs($user);

        return $user;
    }

    /**
     * 确保默认租户存在
     */
    protected function ensureDefaultTenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['code' => 'DEFAULT'],
            ['id' => 1, 'name' => '默认租户', 'status' => 1]
        );
    }
}

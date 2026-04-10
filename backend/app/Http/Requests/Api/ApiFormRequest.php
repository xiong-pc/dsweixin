<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Api FormRequest 基类
 *
 * 提供权限检查工具方法，所有 Api 模块的 FormRequest 继承此类。
 * 主要权限控制由路由中间件（auth:api / tenant / super-admin）承担，
 * authorize() 在此之上提供额外的防御层。
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * 检查当前用户是否为超级管理员
     */
    protected function isSuperAdmin(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * 检查当前用户是否拥有指定权限标识
     * 权限标识对应 menus.permission 字段（如 'system:user:add'）
     */
    protected function hasPermission(string $permission): bool
    {
        $permissions = $this->user()?->getAttribute('_cached_permissions');

        if ($permissions === null) {
            if ($this->isSuperAdmin()) {
                return true;
            }
            // 此处可扩展为从 cache 或 session 读取权限列表
        }

        return in_array('*', $permissions ?? []) || in_array($permission, $permissions ?? []);
    }
}

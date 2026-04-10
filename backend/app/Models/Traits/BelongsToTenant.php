<?php

namespace App\Models\Traits;

use App\Scopes\TenantScope;

trait BelongsToTenant
{
    /**
     * 为 true 时，租户隔离包含 tenant_id 为 null 的系统公共数据行（与迁移注释一致）。
     */
    public static function includeNullTenantInScope(): bool
    {
        return false;
    }

    /**
     * 为 true 时，租户隔离包含 tenant_id = 0 的平台公共数据（configs/notices 等表默认值）。
     */
    public static function includeZeroTenantInScope(): bool
    {
        return false;
    }

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (auth()->check() && !$model->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}

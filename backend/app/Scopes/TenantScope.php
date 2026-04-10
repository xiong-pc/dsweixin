<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    private static bool $applying = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$applying) {
            return;
        }

        self::$applying = true;

        try {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user && $user->tenant_id && !$user->isSuperAdmin()) {
                    $column = $model->getTable().'.tenant_id';
                    if ($model::includeNullTenantInScope()) {
                        $builder->where(function ($q) use ($user, $column) {
                            $q->where($column, $user->tenant_id)->orWhereNull($column);
                        });
                    } elseif ($model::includeZeroTenantInScope()) {
                        $builder->where(function ($q) use ($user, $column) {
                            $q->where($column, $user->tenant_id)->orWhere($column, 0);
                        });
                    } else {
                        $builder->where($column, $user->tenant_id);
                    }
                }
            }
        } finally {
            self::$applying = false;
        }
    }
}

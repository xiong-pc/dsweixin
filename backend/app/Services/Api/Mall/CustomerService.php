<?php

namespace App\Services\Api\Mall;

use App\Exceptions\BusinessException;
use App\Models\Mall\Customer;
use App\Models\Mall\CustomerGroup;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 客户主体管理（admin 端 CRUD）。
 *
 * 注意：客户自助注册 / 登录归 M09-PR35（独立 Passport guard），
 * 这里只负责后台运营对客户的管理（停用 / 改分组 / 软删等）。
 */
class CustomerService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Customer::query()->with('group.translations');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (isset($filters['shop_id']) && $filters['shop_id'] !== '') {
            $query->where('shop_id', $filters['shop_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['group_id'])) {
            $query->where('group_id', (int) $filters['group_id']);
        }
        if (! empty($filters['keywords'])) {
            $kw = (string) $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('email', 'like', "%{$kw}%")
                    ->orWhere('phone', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%");
            });
        }

        return $query->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * 修改客户：仅允许改 status / group_id / locale / currency / name 等运营字段。
     * email / phone / password 不允许后台改（避免账户接管风险）。
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): void
    {
        $allowed = [];
        foreach (['status', 'group_id', 'name', 'locale', 'currency'] as $key) {
            if (array_key_exists($key, $data)) {
                $allowed[$key] = $data[$key];
            }
        }

        // 客户解绑分组（group_id=null）允许通过；指定 group_id 时必须存在且属于同租户
        if (isset($allowed['group_id'])) {
            $exists = CustomerGroup::query()
                ->where('id', (int) $allowed['group_id'])
                ->where('tenant_id', $customer->tenant_id)
                ->exists();
            if (! $exists) {
                throw new BusinessException('api.customer_group_not_found');
            }
        }

        $customer->update($allowed);
    }

    /**
     * 软删除客户（订单 / 地址不动，避免破坏历史关联）。
     */
    public function delete(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->delete();
        });
    }
}

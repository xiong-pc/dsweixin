<?php

namespace App\Services\Api;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ShopService
{
    public function list(User $user, array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Shop::query();

        if (! $user->isSuperAdmin()) {
            if ($user->tenant_id) {
                $query->where('tenant_id', $user->tenant_id);
            } else {
                $query->whereRaw('0 = 1');
            }
        } elseif (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('code', 'like', "%{$kw}%")
                    ->orWhere('subdomain', 'like', "%{$kw}%");
            });
        } elseif (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('sort')->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): Shop
    {
        return Shop::create($data);
    }

    public function update(Shop $shop, array $data): void
    {
        $shop->update($data);
    }

    public function delete(Shop $shop): void
    {
        $shop->delete();
    }
}

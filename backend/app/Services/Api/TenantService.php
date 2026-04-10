<?php

namespace App\Services\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantService
{
    public function list(User $user, array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Tenant::query();

        if (!$user->isSuperAdmin()) {
            if ($user->tenant_id) {
                $query->where('id', $user->tenant_id);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%");
            });
        } elseif (!empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }

    public function update(Tenant $tenant, array $data): void
    {
        $tenant->update($data);
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }
}

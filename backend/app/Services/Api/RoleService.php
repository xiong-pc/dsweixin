<?php

namespace App\Services\Api;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoleService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Role::query();

        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        return $query->orderBy('sort')->paginate($pageSize);
    }

    public function create(array $data, array $menuIds = []): Role
    {
        $role = Role::create($data);

        if (!empty($menuIds)) {
            $role->menus()->sync($menuIds);
        }

        return $role;
    }

    public function update(Role $role, array $data, ?array $menuIds = null): void
    {
        $role->update($data);

        if ($menuIds !== null) {
            $role->menus()->sync($menuIds);
        }
    }

    public function delete(Role $role): void
    {
        $role->menus()->detach();
        $role->users()->detach();
        $role->delete();
    }

    public function getMenuIds(Role $role): Collection
    {
        return $role->menus->pluck('id');
    }

    public function updateMenus(Role $role, array $menuIds): void
    {
        $role->menus()->sync($menuIds);
    }
}

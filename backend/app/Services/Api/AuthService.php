<?php

namespace App\Services\Api;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;

class AuthService
{
    public function attemptLogin(string $username, string $password): ?User
    {
        $user = User::withoutGlobalScopes()->where('username', $username)->first();

        if (!$user || !password_verify($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function createToken(User $user): array
    {
        $token = $user->createToken('Personal Access Token');

        return [
            'accessToken' => $token->accessToken,
            'tokenType'   => 'Bearer',
            'expiresIn'   => config('passport.token_expire_days', 15) * 86400,
        ];
    }

    public function getUserProfile(User $user): array
    {
        $user->load(['roles', 'dept']);

        $roles = $user->roles->pluck('code')->toArray();
        $permissions = $this->resolvePermissions($user, $roles);

        return [
            'userId'      => $user->id,
            'username'    => $user->username,
            'nickname'    => $user->nickname,
            'avatar'      => $user->avatar,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'gender'      => $user->gender,
            'status'      => $user->status,
            'deptName'    => $user->dept?->name,
            'roles'       => $roles,
            'permissions' => $permissions,
        ];
    }

    public function getRouteTree(User $user): array
    {
        $roles = $user->roles->pluck('code')->toArray();

        $menus = in_array('SUPER_ADMIN', $roles)
            ? Menu::whereIn('type', [1, 2, 4])->orderBy('sort')->get()
            : $this->getMenusByRoles($user)->whereIn('type', [1, 2, 4]);

        return $this->buildMenuTree($menus, 0);
    }

    private function resolvePermissions(User $user, array $roles): array
    {
        if (in_array('SUPER_ADMIN', $roles)) {
            return ['*'];
        }

        $menuIds = $user->roles()->with('menus')->get()
            ->pluck('menus')->flatten()->pluck('id')->unique();

        return Menu::whereIn('id', $menuIds)
            ->where('type', 3)
            ->pluck('permission')
            ->filter()
            ->values()
            ->toArray();
    }

    private function getMenusByRoles(User $user): Collection
    {
        $menuIds = $user->roles()->with('menus')->get()
            ->pluck('menus')->flatten()->pluck('id')->unique();

        return Menu::whereIn('id', $menuIds)->orderBy('sort')->get();
    }

    private function buildMenuTree(iterable $menus, int $parentId): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu->parent_id == $parentId) {
                $node = [
                    'path'      => $menu->path,
                    'component' => $menu->component,
                    'name'      => $menu->name,
                    'redirect'  => $menu->redirect ?: null,
                    'meta'      => [
                        'title'  => $menu->name,
                        'icon'   => $menu->icon,
                        'hidden' => !$menu->visible,
                    ],
                ];
                $children = $this->buildMenuTree($menus, $menu->id);
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }
}

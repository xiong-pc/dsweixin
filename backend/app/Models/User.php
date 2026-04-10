<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'dept_id', 'username', 'name', 'nickname', 'email', 'phone',
        'avatar', 'password', 'gender', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function dept()
    {
        return $this->belongsTo(Dept::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->withoutGlobalScopes()->where('code', 'SUPER_ADMIN')->exists();
    }

    /**
     * 角色是否分配了「租户管理」菜单（与 /auth/routes 一致，超管恒为 true）。
     */
    public function hasTenantManagementMenu(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $menuIds = $this->roles()->with('menus')->get()
            ->pluck('menus')->flatten()->pluck('id')->unique();

        return Menu::where('component', 'system/tenant/index')
            ->whereIn('id', $menuIds)
            ->exists();
    }

    /**
     * 是否拥有某按钮权限（menus.type=3 的 permission，超管恒为 true）。
     */
    public function hasPermissionKey(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($permission === '') {
            return false;
        }

        $menuIds = $this->roles()->with('menus')->get()
            ->pluck('menus')->flatten()->pluck('id')->unique();

        return Menu::whereIn('id', $menuIds)
            ->where('type', 3)
            ->where('permission', $permission)
            ->exists();
    }
}

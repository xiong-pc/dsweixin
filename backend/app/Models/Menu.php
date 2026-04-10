<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use BelongsToTenant;

    public static function includeZeroTenantInScope(): bool
    {
        return true;
    }

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'type', 'path', 'component', 'permission',
        'icon', 'sort', 'visible', 'redirect',
    ];

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort');
    }

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_menus');
    }
}

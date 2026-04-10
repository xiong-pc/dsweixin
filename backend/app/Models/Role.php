<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'data_scope', 'sort', 'status', 'remark',
    ];

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'role_menus');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function depts()
    {
        return $this->belongsToMany(Dept::class, 'role_depts');
    }
}

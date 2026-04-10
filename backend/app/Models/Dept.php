<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Dept extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'sort', 'status',
    ];

    public function children()
    {
        return $this->hasMany(Dept::class, 'parent_id')->orderBy('sort');
    }

    public function parent()
    {
        return $this->belongsTo(Dept::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}

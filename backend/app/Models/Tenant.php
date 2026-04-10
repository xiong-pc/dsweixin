<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'status', 'contact_name', 'contact_phone', 'expired_at', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function depts()
    {
        return $this->hasMany(Dept::class);
    }
}

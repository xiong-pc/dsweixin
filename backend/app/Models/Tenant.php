<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'status', 'contact_name', 'contact_phone', 'expired_at', 'remark',
        'plan_id', 'primary_domain', 'default_locale', 'default_currency', 'industry',
    ];

    protected function casts(): array
    {
        return [
            'expired_at' => 'datetime',
            'plan_id' => 'integer',
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

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}

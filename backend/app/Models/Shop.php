<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'subdomain', 'locale', 'currency',
        'timezone', 'theme_id', 'status', 'sort', 'remark',
    ];

    protected $attributes = [
        'locale' => 'zh-CN',
        'currency' => 'CNY',
        'timezone' => 'Asia/Shanghai',
        'status' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'theme_id' => 'integer',
            'status' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

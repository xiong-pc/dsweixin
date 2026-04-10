<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Dict extends Model
{
    use BelongsToTenant;

    public static function includeZeroTenantInScope(): bool
    {
        return true;
    }

    protected $fillable = ['tenant_id', 'name', 'code', 'status', 'remark'];

    public function items()
    {
        return $this->hasMany(DictItem::class, 'dict_id')->orderBy('sort');
    }
}

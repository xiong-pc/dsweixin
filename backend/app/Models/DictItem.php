<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DictItem extends Model
{
    use BelongsToTenant;

    public static function includeZeroTenantInScope(): bool
    {
        return true;
    }

    protected $fillable = ['tenant_id', 'dict_id', 'label', 'value', 'sort', 'status', 'remark'];

    public function dict()
    {
        return $this->belongsTo(Dict::class, 'dict_id');
    }
}

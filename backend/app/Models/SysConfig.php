<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SysConfig extends Model
{
    use BelongsToTenant;

    public static function includeZeroTenantInScope(): bool
    {
        return true;
    }

    protected $table = 'configs';

    protected $fillable = ['tenant_id', 'name', 'key', 'value', 'type', 'remark'];
}

<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 物流 / 快递方式（租户级）。
 *
 * 一个 method 关联 N 条 rates（按 zone + 重量分段计费）。
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $carrier
 * @property int $sort
 * @property int $status
 */
class ShippingMethod extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'code', 'carrier', 'sort', 'status',
    ];

    protected $attributes = [
        'code' => '',
        'carrier' => '',
        'sort' => 0,
        'status' => 1,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'sort' => 'integer',
            'status' => 'integer',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }
}

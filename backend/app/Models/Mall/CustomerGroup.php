<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 客户分组（VIP/普通/批发等），租户级。
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $discount_rate
 * @property int $sort
 * @property int $status
 */
class CustomerGroup extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'code', 'discount_rate', 'sort', 'status',
    ];

    protected $attributes = [
        'code' => '',
        'discount_rate' => 1.0000,
        'sort' => 0,
        'status' => 1,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'discount_rate' => 'decimal:4',
            'sort' => 'integer',
            'status' => 'integer',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'group_id');
    }
}

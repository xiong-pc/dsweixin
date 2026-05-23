<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $shop_id
 * @property string $code
 * @property string $driver
 * @property string $name
 * @property array<string, mixed>|null $config
 * @property int $status
 * @property int $sort
 */
class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'shop_id', 'code', 'driver', 'name', 'config', 'status', 'sort',
    ];

    protected $attributes = [
        'name' => '',
        'status' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'shop_id' => 'integer',
            'config' => 'array',
            'status' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function isEnabled(): bool
    {
        return (int) $this->status === 1;
    }
}

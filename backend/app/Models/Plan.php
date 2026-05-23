<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'description',
        'price_monthly', 'price_yearly', 'currency', 'billing_period', 'trial_days',
        'max_shops', 'max_products', 'max_orders_per_month', 'max_users',
        'max_storage_mb', 'max_languages', 'max_currencies',
        'features', 'status', 'sort',
    ];

    protected $attributes = [
        'currency' => 'CNY',
        'billing_period' => 'monthly',
        'status' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'trial_days' => 'integer',
            'max_shops' => 'integer',
            'max_products' => 'integer',
            'max_orders_per_month' => 'integer',
            'max_users' => 'integer',
            'max_storage_mb' => 'integer',
            'max_languages' => 'integer',
            'max_currencies' => 'integer',
            'features' => 'array',
            'status' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}

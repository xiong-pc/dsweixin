<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'decimal_places', 'is_active', 'sort',
    ];

    protected $attributes = [
        'decimal_places' => 2,
        'is_active' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'integer',
            'sort' => 'integer',
        ];
    }
}

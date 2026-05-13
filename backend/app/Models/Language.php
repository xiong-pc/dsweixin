<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code', 'name', 'native_name', 'direction', 'is_active', 'sort',
    ];

    protected $attributes = [
        'direction' => 'ltr',
        'is_active' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'integer',
            'sort' => 'integer',
        ];
    }
}

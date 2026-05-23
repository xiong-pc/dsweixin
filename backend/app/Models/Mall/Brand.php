<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'code', 'logo', 'website', 'sort', 'status',
    ];

    protected $attributes = [
        'code' => '',
        'logo' => '',
        'website' => '',
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
}

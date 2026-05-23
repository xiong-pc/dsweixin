<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandTranslation extends Model
{
    protected $fillable = [
        'brand_id', 'locale', 'name', 'description',
    ];

    protected $attributes = [
        'description' => '',
    ];

    protected function casts(): array
    {
        return [
            'brand_id' => 'integer',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}

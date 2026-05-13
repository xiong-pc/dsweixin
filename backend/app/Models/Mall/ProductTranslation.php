<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    protected $fillable = [
        'product_id', 'locale',
        'name', 'slug', 'short_description', 'description',
        'seo_title', 'seo_keywords', 'seo_description',
    ];

    protected $attributes = [
        'slug' => '',
        'short_description' => '',
        'seo_title' => '',
        'seo_keywords' => '',
        'seo_description' => '',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

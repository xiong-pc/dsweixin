<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTranslation extends Model
{
    protected $fillable = [
        'category_id', 'locale', 'name', 'description',
    ];

    protected $attributes = [
        'description' => '',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

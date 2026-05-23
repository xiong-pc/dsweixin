<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'parent_id', 'code', 'cover_image', 'sort', 'status',
    ];

    protected $attributes = [
        'parent_id' => 0,
        'code' => '',
        'cover_image' => '',
        'sort' => 0,
        'status' => 1,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'parent_id' => 'integer',
            'sort' => 'integer',
            'status' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }
}

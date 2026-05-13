<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeTranslation extends Model
{
    protected $fillable = [
        'attribute_id', 'locale', 'name',
    ];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}

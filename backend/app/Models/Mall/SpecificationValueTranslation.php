<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecificationValueTranslation extends Model
{
    protected $fillable = [
        'specification_value_id', 'locale', 'name',
    ];

    protected function casts(): array
    {
        return [
            'specification_value_id' => 'integer',
        ];
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(SpecificationValue::class, 'specification_value_id');
    }
}

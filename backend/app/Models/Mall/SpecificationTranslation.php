<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecificationTranslation extends Model
{
    protected $fillable = [
        'specification_id', 'locale', 'name',
    ];

    protected function casts(): array
    {
        return [
            'specification_id' => 'integer',
        ];
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }
}

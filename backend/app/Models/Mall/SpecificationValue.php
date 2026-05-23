<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecificationValue extends Model
{
    use HasTranslations;

    protected $fillable = [
        'specification_id', 'code', 'color_hex', 'sort',
    ];

    protected $attributes = [
        'color_hex' => '',
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'specification_id' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }

    protected function translationModel(): string
    {
        return SpecificationValueTranslation::class;
    }

    protected function translationForeignKey(): string
    {
        return 'specification_value_id';
    }
}

<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends Model
{
    use HasTranslations;

    protected $fillable = [
        'attribute_id', 'code', 'sort',
    ];

    protected $attributes = [
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    protected function translationModel(): string
    {
        return AttributeValueTranslation::class;
    }

    protected function translationForeignKey(): string
    {
        return 'attribute_value_id';
    }
}

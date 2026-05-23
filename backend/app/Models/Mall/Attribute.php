<?php

namespace App\Models\Mall;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasTranslations;

    protected $fillable = [
        'tenant_id', 'code', 'status', 'sort',
    ];

    protected $attributes = [
        'status' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'status' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }
}

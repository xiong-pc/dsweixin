<?php

namespace App\Models\Mall;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerGroupTranslation extends Model
{
    protected $fillable = [
        'customer_group_id', 'locale', 'name', 'description',
    ];

    protected $attributes = [
        'description' => '',
    ];

    protected function casts(): array
    {
        return [
            'customer_group_id' => 'integer',
        ];
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}

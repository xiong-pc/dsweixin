<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Zone extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort',
    ];

    protected $attributes = [
        'is_active' => 1,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'zone_countries')->withTimestamps();
    }
}

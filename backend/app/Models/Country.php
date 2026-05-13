<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'code', 'code3', 'name', 'continent', 'phone_code',
        'currency_code', 'locale', 'is_active', 'sort',
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

    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function getTranslation(string $locale): ?string
    {
        $translation = $this->translations->firstWhere('locale', $locale);

        if ($translation === null) {
            return $this->name;
        }

        return $translation->name;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency', 'to_currency', 'rate', 'source', 'fetched_at',
    ];

    protected $attributes = [
        'source' => 'manual',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }

    public static function convert(string $from, string $to, float $amount): ?float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = self::where('from_currency', $from)
            ->where('to_currency', $to)
            ->latest('fetched_at')
            ->value('rate');

        if ($rate === null) {
            return null;
        }

        return (float) $amount * (float) $rate;
    }
}

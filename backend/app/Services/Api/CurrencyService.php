<?php

namespace App\Services\Api;

use App\Models\Currency;
use Illuminate\Pagination\LengthAwarePaginator;

class CurrencyService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Currency::query();

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): Currency
    {
        return Currency::create($data);
    }

    public function update(Currency $currency, array $data): void
    {
        $currency->update($data);
    }

    public function delete(Currency $currency): void
    {
        $currency->delete();
    }
}

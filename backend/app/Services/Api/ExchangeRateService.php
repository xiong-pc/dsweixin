<?php

namespace App\Services\Api;

use App\Models\ExchangeRate;
use Illuminate\Pagination\LengthAwarePaginator;

class ExchangeRateService
{
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = ExchangeRate::query();

        if (! empty($filters['from_currency'])) {
            $query->where('from_currency', strtoupper($filters['from_currency']));
        }

        if (! empty($filters['to_currency'])) {
            $query->where('to_currency', strtoupper($filters['to_currency']));
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        return $query->orderBy('from_currency')->orderBy('to_currency')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function upsert(array $data): ExchangeRate
    {
        $data['from_currency'] = strtoupper($data['from_currency']);
        $data['to_currency'] = strtoupper($data['to_currency']);
        $data['fetched_at'] = $data['fetched_at'] ?? now();

        return ExchangeRate::updateOrCreate(
            [
                'from_currency' => $data['from_currency'],
                'to_currency' => $data['to_currency'],
            ],
            [
                'rate' => $data['rate'],
                'source' => $data['source'] ?? 'manual',
                'fetched_at' => $data['fetched_at'],
            ]
        );
    }

    public function delete(ExchangeRate $rate): void
    {
        $rate->delete();
    }
}

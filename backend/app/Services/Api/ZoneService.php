<?php

namespace App\Services\Api;

use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ZoneService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Zone::query()->with('countries:id,code,name');

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

    public function create(array $data): Zone
    {
        return DB::transaction(function () use ($data) {
            $countryIds = $data['country_ids'] ?? [];
            unset($data['country_ids']);

            $zone = Zone::create($data);

            if (! empty($countryIds)) {
                $zone->countries()->sync($countryIds);
            }

            return $zone->load('countries:id,code,name');
        });
    }

    public function update(Zone $zone, array $data): void
    {
        DB::transaction(function () use ($zone, $data) {
            $countryIds = $data['country_ids'] ?? null;
            unset($data['country_ids']);

            $zone->update($data);

            if (is_array($countryIds)) {
                $zone->countries()->sync($countryIds);
            }
        });
    }

    public function delete(Zone $zone): void
    {
        DB::transaction(function () use ($zone) {
            $zone->countries()->detach();
            $zone->delete();
        });
    }
}

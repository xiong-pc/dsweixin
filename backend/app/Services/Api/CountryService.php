<?php

namespace App\Services\Api;

use App\Models\Country;
use App\Models\CountryTranslation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CountryService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Country::query()->with('translations');

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhere('code3', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%");
            });
        }

        if (! empty($filters['continent'])) {
            $query->where('continent', $filters['continent']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): Country
    {
        return DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $country = Country::create($data);

            foreach ($translations as $tr) {
                if (! empty($tr['locale']) && ! empty($tr['name'])) {
                    CountryTranslation::create([
                        'country_id' => $country->id,
                        'locale' => $tr['locale'],
                        'name' => $tr['name'],
                    ]);
                }
            }

            return $country->load('translations');
        });
    }

    public function update(Country $country, array $data): void
    {
        DB::transaction(function () use ($country, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations']);

            $country->update($data);

            if (is_array($translations)) {
                $country->translations()->delete();
                foreach ($translations as $tr) {
                    if (! empty($tr['locale']) && ! empty($tr['name'])) {
                        CountryTranslation::create([
                            'country_id' => $country->id,
                            'locale' => $tr['locale'],
                            'name' => $tr['name'],
                        ]);
                    }
                }
            }
        });
    }

    public function delete(Country $country): void
    {
        DB::transaction(function () use ($country) {
            $country->translations()->delete();
            $country->delete();
        });
    }
}

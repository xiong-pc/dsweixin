<?php

namespace App\Services\Api\Mall;

use App\Exceptions\BusinessException;
use App\Models\Mall\Brand;
use App\Models\Mall\BrandTranslation;
use App\Models\Mall\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BrandService
{
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Brand::query()->with('translations');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhereHas('translations', fn ($qt) => $qt->where('name', 'like', "%{$kw}%"));
            });
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(int $tenantId, array $data): Brand
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['tenant_id'] = $tenantId;
            $brand = Brand::create($data);

            if (! empty($translations)) {
                $this->saveTranslations($brand, $translations);
            }

            return $brand->load('translations');
        });
    }

    public function update(Brand $brand, array $data): void
    {
        DB::transaction(function () use ($brand, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['tenant_id']);

            $brand->update($data);

            if (is_array($translations)) {
                $brand->translations()->delete();
                $this->saveTranslations($brand->fresh(), $translations);
            }
        });
    }

    public function delete(Brand $brand): void
    {
        if (Product::where('brand_id', $brand->id)->exists()) {
            throw new BusinessException('api.brand_has_products');
        }

        DB::transaction(function () use ($brand) {
            $brand->translations()->delete();
            $brand->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function saveTranslations(Brand $brand, array $translations): void
    {
        foreach ($translations as $t) {
            $locale = $t['locale'] ?? null;
            $name = $t['name'] ?? null;
            if (! is_string($locale) || $locale === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $description = $t['description'] ?? '';
            if (! is_string($description)) {
                $description = '';
            }

            BrandTranslation::updateOrCreate(
                ['brand_id' => $brand->id, 'locale' => $locale],
                ['name' => $name, 'description' => $description]
            );
        }
    }
}

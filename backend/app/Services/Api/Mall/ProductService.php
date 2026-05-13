<?php

namespace App\Services\Api\Mall;

use App\Models\Mall\Product;
use App\Models\Mall\ProductTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Product::query()->with(['translations']);

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (array_key_exists('shop_id', $filters)) {
            $shopId = $filters['shop_id'];
            if ($shopId === null) {
                $query->whereNull('shop_id');
            } elseif ($shopId !== '') {
                $query->where('shop_id', $shopId);
            }
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('sku_prefix', 'like', "%{$kw}%")
                    ->orWhereHas('translations', fn ($qt) => $qt->where('name', 'like', "%{$kw}%"));
            });
        }

        return $query->orderBy('sort')->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(int $tenantId, array $data): Product
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['tenant_id'] = $tenantId;
            $data['shop_id'] = $this->normalizeShopId($data['shop_id'] ?? null);

            $product = Product::create($data);

            if (! empty($translations)) {
                $this->saveTranslations($product, $translations);
            }

            return $product->load('translations');
        });
    }

    public function update(Product $product, array $data): void
    {
        DB::transaction(function () use ($product, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['tenant_id']);

            if (array_key_exists('shop_id', $data)) {
                $data['shop_id'] = $this->normalizeShopId($data['shop_id']);
            }

            $product->update($data);

            if (is_array($translations)) {
                // 删除当前未提供的 locale，然后 upsert
                $product->translations()->delete();
                $this->saveTranslations($product->fresh(), $translations);
            }
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            // 翻译表硬删（与商品 soft delete 不同），SPU 用软删
            $product->translations()->delete();
            $product->delete();
        });
    }

    /**
     * 校验 slug 在同 shop+locale 内唯一。
     *
     * @param  array<int, array<string, mixed>>  $translations
     * @return array<string, string> 错误信息（locale => message）
     */
    public function validateSlugUniqueness(
        int $tenantId,
        ?int $shopId,
        array $translations,
        ?int $excludeProductId = null
    ): array {
        $errors = [];
        foreach ($translations as $index => $t) {
            $slug = $t['slug'] ?? '';
            $locale = $t['locale'] ?? '';
            if (! is_string($slug) || $slug === '' || ! is_string($locale) || $locale === '') {
                continue;
            }

            // shop_id 必须等价比较（NULL == NULL）
            $exists = ProductTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->whereHas('product', function (Builder $q) use ($tenantId, $shopId, $excludeProductId) {
                    $q->where('tenant_id', $tenantId);
                    if ($shopId === null) {
                        $q->whereNull('shop_id');
                    } else {
                        $q->where('shop_id', $shopId);
                    }
                    if ($excludeProductId !== null) {
                        $q->where('id', '!=', $excludeProductId);
                    }
                })
                ->exists();

            if ($exists) {
                $errors["translations.{$index}.slug"] = trans('validation.unique', ['attribute' => 'slug']);
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function saveTranslations(Product $product, array $translations): void
    {
        foreach ($translations as $t) {
            $locale = $t['locale'] ?? null;
            if (! is_string($locale) || $locale === '') {
                continue;
            }
            $name = $t['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            ProductTranslation::updateOrCreate(
                ['product_id' => $product->id, 'locale' => $locale],
                [
                    'name' => $name,
                    'slug' => isset($t['slug']) && is_string($t['slug']) ? $t['slug'] : '',
                    'short_description' => isset($t['short_description']) && is_string($t['short_description']) ? $t['short_description'] : '',
                    'description' => isset($t['description']) && is_string($t['description']) ? $t['description'] : null,
                    'seo_title' => isset($t['seo_title']) && is_string($t['seo_title']) ? $t['seo_title'] : '',
                    'seo_keywords' => isset($t['seo_keywords']) && is_string($t['seo_keywords']) ? $t['seo_keywords'] : '',
                    'seo_description' => isset($t['seo_description']) && is_string($t['seo_description']) ? $t['seo_description'] : '',
                ]
            );
        }
    }

    private function normalizeShopId(mixed $shopId): ?int
    {
        if ($shopId === null || $shopId === '' || $shopId === 0 || $shopId === '0') {
            return null;
        }

        return (int) $shopId;
    }
}

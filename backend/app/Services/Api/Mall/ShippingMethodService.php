<?php

namespace App\Services\Api\Mall;

use App\Exceptions\BusinessException;
use App\Models\Mall\ShippingMethod;
use App\Models\Mall\ShippingMethodTranslation;
use App\Models\Mall\ShippingRate;
use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ShippingMethodService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = ShippingMethod::query()->with(['translations', 'rates']);

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keywords'])) {
            $kw = (string) $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhere('carrier', 'like', "%{$kw}%")
                    ->orWhereHas('translations', fn ($qt) => $qt->where('name', 'like', "%{$kw}%"));
            });
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $tenantId, array $data): ShippingMethod
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = is_array($data['translations'] ?? null) ? $data['translations'] : [];
            $rates = is_array($data['rates'] ?? null) ? $data['rates'] : [];
            unset($data['translations'], $data['rates']);

            $data['tenant_id'] = $tenantId;
            $method = ShippingMethod::create($data);

            $this->saveTranslations($method, $translations);
            $this->saveRates($method, $rates);

            return $method->load(['translations', 'rates']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ShippingMethod $method, array $data): void
    {
        DB::transaction(function () use ($method, $data) {
            $translations = array_key_exists('translations', $data) && is_array($data['translations'])
                ? $data['translations'] : null;
            $rates = array_key_exists('rates', $data) && is_array($data['rates'])
                ? $data['rates'] : null;
            unset($data['translations'], $data['rates'], $data['tenant_id']);

            $method->update($data);

            if ($translations !== null) {
                $method->translations()->delete();
                $this->saveTranslations($method->fresh() ?? $method, $translations);
            }

            // rates 整体替换：put 风格更易理解 + 避免脏数据
            if ($rates !== null) {
                $method->rates()->delete();
                $this->saveRates($method->fresh() ?? $method, $rates);
            }
        });
    }

    public function delete(ShippingMethod $method): void
    {
        DB::transaction(function () use ($method) {
            $method->translations()->delete();
            $method->rates()->delete();
            $method->delete(); // soft delete
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function saveTranslations(ShippingMethod $method, array $translations): void
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

            ShippingMethodTranslation::updateOrCreate(
                ['shipping_method_id' => $method->id, 'locale' => $locale],
                ['name' => $name, 'description' => $description]
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rates
     *
     * @throws BusinessException
     */
    private function saveRates(ShippingMethod $method, array $rates): void
    {
        foreach ($rates as $r) {
            $zoneId = isset($r['zone_id']) ? (int) $r['zone_id'] : 0;
            if ($zoneId <= 0) {
                throw new BusinessException('api.shipping_rate_zone_required');
            }

            if (! Zone::query()->whereKey($zoneId)->exists()) {
                throw new BusinessException('api.shipping_rate_zone_not_found');
            }

            $weightMin = isset($r['weight_min']) ? (int) $r['weight_min'] : 0;
            $weightMax = isset($r['weight_max']) ? (int) $r['weight_max'] : 0;

            // weight_max=0 视为无上限（合法）；否则必须 > weight_min
            if ($weightMax !== 0 && $weightMax <= $weightMin) {
                throw new BusinessException('api.shipping_rate_weight_range_invalid');
            }

            ShippingRate::create([
                'shipping_method_id' => $method->id,
                'zone_id' => $zoneId,
                'weight_min' => max(0, $weightMin),
                'weight_max' => max(0, $weightMax),
                'price' => isset($r['price']) ? (float) $r['price'] : 0,
                'free_threshold' => isset($r['free_threshold']) ? (float) $r['free_threshold'] : 0,
            ]);
        }
    }
}

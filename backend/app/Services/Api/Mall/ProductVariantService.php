<?php

namespace App\Services\Api\Mall;

use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Mall\SpecificationValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    public function listForProduct(Product $product): Collection
    {
        return $product->variants()
            ->with('specificationValues.translations')
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    public function create(Product $product, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $data) {
            $specValueIds = $this->extractSpecValueIds($data);
            unset($data['specification_value_ids']);

            $data['product_id'] = $product->id;
            $variant = ProductVariant::create($data);

            if ($specValueIds !== []) {
                $variant->specificationValues()->sync($specValueIds);
            }

            return $variant->load('specificationValues.translations');
        });
    }

    public function update(ProductVariant $variant, array $data): void
    {
        DB::transaction(function () use ($variant, $data) {
            $hasSpecKey = array_key_exists('specification_value_ids', $data);
            $specValueIds = $this->extractSpecValueIds($data);
            unset($data['specification_value_ids'], $data['product_id']);

            $variant->update($data);

            if ($hasSpecKey) {
                $variant->specificationValues()->sync($specValueIds);
            }
        });
    }

    public function delete(ProductVariant $variant): void
    {
        DB::transaction(function () use ($variant) {
            // pivot 关系硬删
            $variant->specificationValues()->detach();
            $variant->delete();
        });
    }

    /**
     * 矩阵生成：给定多个规格组的值数组，生成所有变体组合。
     *
     * @param  array<int, mixed>  $specGroups  形如 [[1,2,3], [10,11]] 颜色×尺码
     * @return array<int, array<int, int>> 笛卡尔积结果
     */
    public function generateMatrix(array $specGroups): array
    {
        $specGroups = array_filter($specGroups, fn ($g) => is_array($g) && $g !== []);
        if ($specGroups === []) {
            return [];
        }

        $result = [[]];
        foreach ($specGroups as $group) {
            $newResult = [];
            foreach ($result as $combo) {
                foreach ($group as $value) {
                    $newResult[] = array_merge($combo, [(int) $value]);
                }
            }
            $result = $newResult;
        }

        return $result;
    }

    /**
     * 校验 specification_value_ids 在数据库中存在。
     *
     * @return array<int, int> 存在的 ID 列表
     */
    public function filterExistingSpecValueIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return SpecificationValue::whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int, int>
     */
    private function extractSpecValueIds(array $data): array
    {
        $raw = $data['specification_value_ids'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

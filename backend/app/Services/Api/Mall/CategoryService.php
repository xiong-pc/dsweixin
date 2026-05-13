<?php

namespace App\Services\Api\Mall;

use App\Exceptions\BusinessException;
use App\Models\Mall\Category;
use App\Models\Mall\CategoryTranslation;
use App\Models\Mall\Product;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * 树形列表：扁平 query → 递归组装。
     */
    public function tree(array $filters): array
    {
        $query = Category::query()->with('translations');

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

        $items = $query->orderBy('sort')->orderBy('id')->get()->toArray();

        return $this->buildTree($items, 0);
    }

    public function create(int $tenantId, array $data): Category
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['tenant_id'] = $tenantId;
            $data['parent_id'] = (int) ($data['parent_id'] ?? 0);

            if ($data['parent_id'] > 0) {
                $this->ensureParentInSameTenant($tenantId, $data['parent_id']);
            }

            $category = Category::create($data);

            if (! empty($translations)) {
                $this->saveTranslations($category, $translations);
            }

            return $category->load('translations');
        });
    }

    public function update(Category $category, array $data): void
    {
        DB::transaction(function () use ($category, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['tenant_id']);

            if (array_key_exists('parent_id', $data)) {
                $newParentId = (int) $data['parent_id'];
                $this->ensureNoCycle($category, $newParentId);
                if ($newParentId > 0) {
                    $this->ensureParentInSameTenant((int) $category->tenant_id, $newParentId);
                }
                $data['parent_id'] = $newParentId;
            }

            $category->update($data);

            if (is_array($translations)) {
                $category->translations()->delete();
                $this->saveTranslations($category->fresh(), $translations);
            }
        });
    }

    public function delete(Category $category): void
    {
        // 删除前置检查：不能有子类目
        if (Category::where('parent_id', $category->id)->exists()) {
            throw new BusinessException('api.category_has_children');
        }

        // 不能有关联商品
        if (Product::where('category_id', $category->id)->exists()) {
            throw new BusinessException('api.category_has_products');
        }

        DB::transaction(function () use ($category) {
            $category->translations()->delete();
            $category->delete();
        });
    }

    /**
     * 保存多字段翻译（含 name + description）。
     *
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function saveTranslations(Category $category, array $translations): void
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

            CategoryTranslation::updateOrCreate(
                ['category_id' => $category->id, 'locale' => $locale],
                ['name' => $name, 'description' => $description]
            );
        }
    }

    /**
     * 批量更新排序（拖拽）。
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function reorder(int $tenantId, array $items): void
    {
        DB::transaction(function () use ($tenantId, $items) {
            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $category = Category::where('tenant_id', $tenantId)->find($id);
                if ($category === null) {
                    continue;
                }

                $update = [];
                if (array_key_exists('parent_id', $item)) {
                    $newParentId = (int) $item['parent_id'];
                    $this->ensureNoCycle($category, $newParentId);
                    if ($newParentId > 0) {
                        $this->ensureParentInSameTenant($tenantId, $newParentId);
                    }
                    $update['parent_id'] = $newParentId;
                }
                if (array_key_exists('sort', $item)) {
                    $update['sort'] = (int) $item['sort'];
                }

                if ($update !== []) {
                    $category->update($update);
                }
            }
        });
    }

    /**
     * 校验父类目与子类目在同一租户。
     */
    private function ensureParentInSameTenant(int $tenantId, int $parentId): void
    {
        $parent = Category::find($parentId);
        if ($parent === null || (int) $parent->tenant_id !== $tenantId) {
            throw new BusinessException('api.invalid_parent_category');
        }
    }

    /**
     * 防循环：新 parent 不能是自己或自己的后代。
     */
    private function ensureNoCycle(Category $category, int $newParentId): void
    {
        if ($newParentId === 0) {
            return;
        }
        if ($newParentId === (int) $category->id) {
            throw new BusinessException('api.category_cycle');
        }

        // 收集所有后代 id（包括间接子节点）
        $descendantIds = $this->collectDescendantIds($category);
        if (in_array($newParentId, $descendantIds, true)) {
            throw new BusinessException('api.category_cycle');
        }
    }

    /**
     * @return array<int, int>
     */
    private function collectDescendantIds(Category $category): array
    {
        $ids = [];
        $stack = [(int) $category->id];

        while ($stack !== []) {
            $currentId = array_shift($stack);
            $childIds = Category::where('parent_id', $currentId)->pluck('id')->all();
            foreach ($childIds as $childId) {
                $childId = (int) $childId;
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * 递归构建树。
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $items, int $parentId): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int) ($item['parent_id'] ?? 0) === $parentId) {
                $children = $this->buildTree($items, (int) $item['id']);
                if ($children !== []) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }

        return $tree;
    }
}

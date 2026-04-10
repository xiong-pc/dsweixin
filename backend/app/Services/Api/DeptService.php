<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Dept;

class DeptService
{
    public function tree(array $filters = []): array
    {
        $query = Dept::orderBy('sort');

        if (!empty($filters['keywords'])) {
            $query->where('name', 'like', '%' . $filters['keywords'] . '%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->buildTree($query->get()->toArray(), 0);
    }

    public function create(array $data): Dept
    {
        return Dept::create($data);
    }

    public function update(Dept $dept, array $data): void
    {
        $dept->update($data);
    }

    public function delete(Dept $dept): void
    {
        if (Dept::where('parent_id', $dept->id)->exists()) {
            throw new BusinessException('api.dept_has_children');
        }

        $dept->delete();
    }

    private function buildTree(array $items, int $parentId): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }
}

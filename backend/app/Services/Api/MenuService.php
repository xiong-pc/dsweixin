<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Menu;

class MenuService
{
    public function tree(array $filters = []): array
    {
        $query = Menu::orderBy('sort');

        if (!empty($filters['keywords'])) {
            $query->where('name', 'like', '%' . $filters['keywords'] . '%');
        }

        return $this->buildTree($query->get()->toArray(), 0);
    }

    public function create(array $data): Menu
    {
        return Menu::create($data);
    }

    public function update(Menu $menu, array $data): void
    {
        $menu->update($data);
    }

    public function delete(Menu $menu): void
    {
        if (Menu::where('parent_id', $menu->id)->exists()) {
            throw new BusinessException('api.menu_has_children');
        }

        $menu->roles()->detach();
        $menu->delete();
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

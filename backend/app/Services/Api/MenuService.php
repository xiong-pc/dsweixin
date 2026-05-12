<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Menu;

class MenuService
{
    public function tree(array $filters = []): array
    {
        $query = Menu::orderBy('sort');

        if (! empty($filters['keywords'])) {
            $query->where('name', 'like', '%'.$filters['keywords'].'%');
        }

        return self::buildTree($query->get()->toArray(), 0);
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

    /**
     * 把扁平的 items 列表按 parent_id 构建成嵌套树。
     *
     * 纯函数 (无 $this 状态，便于 Unit test)：输入 array of items + 起始 parentId，
     * 返回嵌套结构，子节点放入 'children' 键（无子节点则不添加 key）。
     */
    public static function buildTree(array $items, int $parentId): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = self::buildTree($items, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }

        return $tree;
    }
}

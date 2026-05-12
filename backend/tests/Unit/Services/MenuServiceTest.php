<?php

namespace Tests\Unit\Services;

use App\Services\Api\MenuService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MenuService::buildTree (pure function).
 *
 * 不加载 Laravel app，直接测试纯逻辑函数。
 */
class MenuServiceTest extends TestCase
{
    #[Test]
    public function build_tree_returns_empty_array_when_items_empty(): void
    {
        $this->assertSame([], MenuService::buildTree([], 0));
    }

    #[Test]
    public function build_tree_returns_single_node_when_one_root_item(): void
    {
        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Root'],
        ];

        $expected = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Root'],
        ];

        $this->assertSame($expected, MenuService::buildTree($items, 0));
    }

    #[Test]
    public function build_tree_nests_children_under_parent(): void
    {
        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Root'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Child A'],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Child B'],
        ];

        $result = MenuService::buildTree($items, 0);

        $this->assertCount(1, $result);
        $this->assertSame('Root', $result[0]['name']);
        $this->assertArrayHasKey('children', $result[0]);
        $this->assertCount(2, $result[0]['children']);
        $this->assertSame('Child A', $result[0]['children'][0]['name']);
        $this->assertSame('Child B', $result[0]['children'][1]['name']);
    }

    #[Test]
    public function build_tree_recurses_for_multi_level_nesting(): void
    {
        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'L1'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'L2'],
            ['id' => 3, 'parent_id' => 2, 'name' => 'L3'],
        ];

        $result = MenuService::buildTree($items, 0);

        $this->assertSame('L1', $result[0]['name']);
        $this->assertSame('L2', $result[0]['children'][0]['name']);
        $this->assertSame('L3', $result[0]['children'][0]['children'][0]['name']);
    }

    #[Test]
    public function build_tree_omits_children_key_when_no_children(): void
    {
        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Leaf'],
        ];

        $result = MenuService::buildTree($items, 0);

        $this->assertArrayNotHasKey('children', $result[0]);
    }

    #[Test]
    public function build_tree_filters_by_specified_parent_id(): void
    {
        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Root1'],
            ['id' => 2, 'parent_id' => 0, 'name' => 'Root2'],
            ['id' => 10, 'parent_id' => 1, 'name' => 'OnlyForRoot1'],
            ['id' => 20, 'parent_id' => 2, 'name' => 'OnlyForRoot2'],
        ];

        // 从 parent_id=1 起始构建，只应包含 OnlyForRoot1
        $result = MenuService::buildTree($items, 1);

        $this->assertCount(1, $result);
        $this->assertSame('OnlyForRoot1', $result[0]['name']);
    }

    #[Test]
    public function build_tree_ignores_items_with_nonexistent_parent(): void
    {
        $items = [
            ['id' => 1, 'parent_id' => 0, 'name' => 'Root'],
            // parent_id=999 不存在于 items 列表，从 0 起始时该项被忽略
            ['id' => 2, 'parent_id' => 999, 'name' => 'Orphan'],
        ];

        $result = MenuService::buildTree($items, 0);

        $this->assertCount(1, $result);
        $this->assertSame('Root', $result[0]['name']);
        // 孤儿项不应出现在任何子树中
        $this->assertArrayNotHasKey('children', $result[0]);
    }

    #[Test]
    public function build_tree_preserves_extra_fields_on_items(): void
    {
        $items = [
            [
                'id' => 1,
                'parent_id' => 0,
                'name' => 'Root',
                'type' => 1,
                'icon' => 'house',
                'sort' => 100,
            ],
        ];

        $result = MenuService::buildTree($items, 0);

        $this->assertSame(1, $result[0]['type']);
        $this->assertSame('house', $result[0]['icon']);
        $this->assertSame(100, $result[0]['sort']);
    }
}

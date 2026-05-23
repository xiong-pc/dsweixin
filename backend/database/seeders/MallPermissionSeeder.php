<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 商城后台按钮权限（M10-PR37）。
 *
 * 必须在 MallMenuSeeder 之后运行——通过 component 路径回查父级 Type 2 菜单 ID。
 *
 * 权限命名规范：`mall:{resource}:{action}`
 *   - resource: product / category / brand / specification / attribute /
 *               order / customer / customer_group / shop / payment /
 *               shipping / i18n
 *   - action:   add / edit / delete / ship / refund / cancel
 */
class MallPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'mall/product/index' => [
                ['name' => '商品新增', 'permission' => 'mall:product:add'],
                ['name' => '商品编辑', 'permission' => 'mall:product:edit'],
                ['name' => '商品删除', 'permission' => 'mall:product:delete'],
            ],
            'mall/category/index' => [
                ['name' => '类目新增', 'permission' => 'mall:category:add'],
                ['name' => '类目编辑', 'permission' => 'mall:category:edit'],
                ['name' => '类目删除', 'permission' => 'mall:category:delete'],
            ],
            'mall/brand/index' => [
                ['name' => '品牌新增', 'permission' => 'mall:brand:add'],
                ['name' => '品牌编辑', 'permission' => 'mall:brand:edit'],
                ['name' => '品牌删除', 'permission' => 'mall:brand:delete'],
            ],
            'mall/specification/index' => [
                ['name' => '规格新增', 'permission' => 'mall:specification:add'],
                ['name' => '规格编辑', 'permission' => 'mall:specification:edit'],
                ['name' => '规格删除', 'permission' => 'mall:specification:delete'],
            ],
            'mall/attribute/index' => [
                ['name' => '属性新增', 'permission' => 'mall:attribute:add'],
                ['name' => '属性编辑', 'permission' => 'mall:attribute:edit'],
                ['name' => '属性删除', 'permission' => 'mall:attribute:delete'],
            ],
            'mall/order/index' => [
                ['name' => '订单发货', 'permission' => 'mall:order:ship'],
                ['name' => '订单退款', 'permission' => 'mall:order:refund'],
                ['name' => '订单取消', 'permission' => 'mall:order:cancel'],
            ],
            'mall/customer/index' => [
                ['name' => '客户编辑', 'permission' => 'mall:customer:edit'],
                ['name' => '客户删除', 'permission' => 'mall:customer:delete'],
            ],
            'mall/customer/group/index' => [
                ['name' => '分组新增', 'permission' => 'mall:customer_group:add'],
                ['name' => '分组编辑', 'permission' => 'mall:customer_group:edit'],
                ['name' => '分组删除', 'permission' => 'mall:customer_group:delete'],
            ],
            'mall/shop/index' => [
                ['name' => '店铺新增', 'permission' => 'mall:shop:add'],
                ['name' => '店铺编辑', 'permission' => 'mall:shop:edit'],
                ['name' => '店铺删除', 'permission' => 'mall:shop:delete'],
            ],
            'mall/payment/index' => [
                ['name' => '支付方式新增', 'permission' => 'mall:payment:add'],
                ['name' => '支付方式编辑', 'permission' => 'mall:payment:edit'],
                ['name' => '支付方式删除', 'permission' => 'mall:payment:delete'],
            ],
            'mall/shipping/index' => [
                ['name' => '物流方式新增', 'permission' => 'mall:shipping:add'],
                ['name' => '物流方式编辑', 'permission' => 'mall:shipping:edit'],
                ['name' => '物流方式删除', 'permission' => 'mall:shipping:delete'],
            ],
            'mall/i18n/index' => [
                ['name' => '语言/币种编辑', 'permission' => 'mall:i18n:edit'],
            ],
        ];

        foreach ($matrix as $component => $buttons) {
            $parentId = Menu::query()
                ->where('component', $component)
                ->where('type', 2)
                ->value('id');

            if ($parentId === null) {
                throw new \RuntimeException(
                    "MallPermissionSeeder: 找不到父菜单 component={$component}，请确认 MallMenuSeeder 已运行。"
                );
            }

            foreach ($buttons as $i => $btn) {
                Menu::create([
                    'tenant_id' => 0,
                    'parent_id' => $parentId,
                    'name' => $btn['name'],
                    'type' => 3,
                    'permission' => $btn['permission'],
                    'sort' => $i + 1,
                    'visible' => 1,
                ]);
            }
        }
    }
}

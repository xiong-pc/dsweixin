<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 商城后台菜单（M10-PR37）。
 *
 * 仅写入 Type 1（目录）和 Type 2（菜单）；Type 3（按钮权限）由 MallPermissionSeeder 处理。
 *
 * 菜单树：
 *   商城
 *   ├── 商品（商品列表 / 类目 / 品牌 / 规格 / 属性）
 *   ├── 订单
 *   ├── 客户（客户列表 / 客户分组）
 *   └── 设置（店铺 / 支付 / 物流 / 多语言币种）
 *
 * 全部 tenant_id=0（系统级公共菜单），所有租户共享，由 SUPER_ADMIN 默认拥有，
 * 普通租户管理员需在角色管理处显式勾选。
 */
class MallMenuSeeder extends Seeder
{
    public function run(): void
    {
        // 一级目录：商城（sort=10 排在系统/基础数据之后）
        $mall = Menu::create([
            'tenant_id' => 0, 'parent_id' => 0,
            'name' => '商城', 'type' => 1,
            'path' => '/mall', 'component' => 'Layout', 'icon' => 'Goods',
            'sort' => 10, 'visible' => 1, 'redirect' => '/mall/product/list',
        ]);

        // 商品（二级目录）
        $product = Menu::create([
            'tenant_id' => 0, 'parent_id' => $mall->id,
            'name' => '商品', 'type' => 1,
            'path' => 'product', 'component' => 'Layout', 'icon' => 'Box',
            'sort' => 1, 'visible' => 1, 'redirect' => '/mall/product/list',
        ]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $product->id, 'name' => '商品列表', 'type' => 2, 'path' => 'list', 'component' => 'mall/product/index', 'icon' => 'List', 'sort' => 1, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $product->id, 'name' => '类目管理', 'type' => 2, 'path' => 'category', 'component' => 'mall/category/index', 'icon' => 'Folder', 'sort' => 2, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $product->id, 'name' => '品牌管理', 'type' => 2, 'path' => 'brand', 'component' => 'mall/brand/index', 'icon' => 'CollectionTag', 'sort' => 3, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $product->id, 'name' => '规格管理', 'type' => 2, 'path' => 'specification', 'component' => 'mall/specification/index', 'icon' => 'Setting', 'sort' => 4, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $product->id, 'name' => '属性管理', 'type' => 2, 'path' => 'attribute', 'component' => 'mall/attribute/index', 'icon' => 'Operation', 'sort' => 5, 'visible' => 1]);

        // 订单（直接挂在商城下，无子目录）
        Menu::create([
            'tenant_id' => 0, 'parent_id' => $mall->id,
            'name' => '订单管理', 'type' => 2,
            'path' => 'order', 'component' => 'mall/order/index', 'icon' => 'Tickets',
            'sort' => 2, 'visible' => 1,
        ]);

        // 客户（二级目录）
        $customer = Menu::create([
            'tenant_id' => 0, 'parent_id' => $mall->id,
            'name' => '客户', 'type' => 1,
            'path' => 'customer', 'component' => 'Layout', 'icon' => 'User',
            'sort' => 3, 'visible' => 1, 'redirect' => '/mall/customer/list',
        ]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $customer->id, 'name' => '客户列表', 'type' => 2, 'path' => 'list', 'component' => 'mall/customer/index', 'icon' => 'UserFilled', 'sort' => 1, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $customer->id, 'name' => '客户分组', 'type' => 2, 'path' => 'group', 'component' => 'mall/customer/group/index', 'icon' => 'Files', 'sort' => 2, 'visible' => 1]);

        // 设置（二级目录）
        $setting = Menu::create([
            'tenant_id' => 0, 'parent_id' => $mall->id,
            'name' => '商城设置', 'type' => 1,
            'path' => 'setting', 'component' => 'Layout', 'icon' => 'Tools',
            'sort' => 4, 'visible' => 1, 'redirect' => '/mall/setting/shop',
        ]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $setting->id, 'name' => '店铺设置', 'type' => 2, 'path' => 'shop', 'component' => 'mall/shop/index', 'icon' => 'Shop', 'sort' => 1, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $setting->id, 'name' => '支付方式', 'type' => 2, 'path' => 'payment', 'component' => 'mall/payment/index', 'icon' => 'CreditCard', 'sort' => 2, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $setting->id, 'name' => '物流方式', 'type' => 2, 'path' => 'shipping', 'component' => 'mall/shipping/index', 'icon' => 'Van', 'sort' => 3, 'visible' => 1]);
        Menu::create(['tenant_id' => 0, 'parent_id' => $setting->id, 'name' => '多语言与币种', 'type' => 2, 'path' => 'i18n', 'component' => 'mall/i18n/index', 'icon' => 'Postcard', 'sort' => 4, 'visible' => 1]);
    }
}

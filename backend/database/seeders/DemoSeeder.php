<?php

namespace Database\Seeders;

use App\Models\Mall\Category;
use App\Models\Mall\CategoryTranslation;
use App\Models\Mall\Product;
use App\Models\Mall\ProductTranslation;
use App\Models\Mall\ProductVariant;
use App\Models\Shop;
use Illuminate\Database\Seeder;

/**
 * 端到端演示数据：默认租户下 1 个店铺 + 1 个类目 + 2 个商品 + 各 1 个 variant。
 *
 * 可重复执行：所有数据用 updateOrCreate 写入，再次跑不会重复插入。
 *
 * 跑法：
 *   php artisan db:seed --class=DemoSeeder
 *
 * 之后在 Nuxt 商城（frontend-shop）启动时通过
 *   NUXT_PUBLIC_FALLBACK_SUBDOMAIN=demo-shop
 * 让默认 host 走这个 shop。
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $shop = Shop::updateOrCreate(
            ['tenant_id' => 1, 'subdomain' => 'demo-shop'],
            [
                'name' => 'Demo Shop',
                'code' => 'DEMO',
                'locale' => 'zh-CN',
                'currency' => 'CNY',
                'timezone' => 'Asia/Shanghai',
                'theme_id' => 0,
                'status' => 1,
                'sort' => 0,
                'remark' => 'E2E demo shop',
            ],
        );

        $category = Category::updateOrCreate(
            ['tenant_id' => 1, 'code' => 'demo-category'],
            ['parent_id' => 0, 'cover_image' => '', 'sort' => 0, 'status' => 1],
        );

        CategoryTranslation::updateOrCreate(
            ['category_id' => $category->id, 'locale' => 'zh-CN'],
            ['name' => '示范类目', 'description' => '端到端演示用类目'],
        );
        CategoryTranslation::updateOrCreate(
            ['category_id' => $category->id, 'locale' => 'en'],
            ['name' => 'Demo Category', 'description' => 'Category for end-to-end demo'],
        );

        $this->seedProduct(
            shopId: $shop->id,
            categoryId: $category->id,
            skuPrefix: 'DEMO-001',
            translations: [
                ['locale' => 'zh-CN', 'name' => '示范商品 A', 'slug' => 'demo-product-a', 'short_description' => '入门款，适合 demo'],
                ['locale' => 'en', 'name' => 'Demo Product A', 'slug' => 'demo-product-a-en', 'short_description' => 'Entry-level demo product'],
            ],
            basePrice: '49.00',
            variantSku: 'DEMO-001-DEFAULT',
            stock: 100,
        );

        $this->seedProduct(
            shopId: $shop->id,
            categoryId: $category->id,
            skuPrefix: 'DEMO-002',
            translations: [
                ['locale' => 'zh-CN', 'name' => '示范商品 B', 'slug' => 'demo-product-b', 'short_description' => '进阶款，库存充足'],
                ['locale' => 'en', 'name' => 'Demo Product B', 'slug' => 'demo-product-b-en', 'short_description' => 'Advanced demo product'],
            ],
            basePrice: '129.00',
            variantSku: 'DEMO-002-DEFAULT',
            stock: 50,
        );

        $this->command?->info('Demo seed ready. shop_id='.$shop->id.' subdomain='.$shop->subdomain);
    }

    /** @param array<int, array<string, string>> $translations */
    private function seedProduct(
        int $shopId,
        int $categoryId,
        string $skuPrefix,
        array $translations,
        string $basePrice,
        string $variantSku,
        int $stock,
    ): void {
        $product = Product::updateOrCreate(
            ['tenant_id' => 1, 'sku_prefix' => $skuPrefix],
            [
                'shop_id' => $shopId,
                'brand_id' => null,
                'category_id' => $categoryId,
                'cover_image' => '',
                'images' => [],
                'base_price' => $basePrice,
                'base_currency' => 'CNY',
                'status' => 1,
                'sort' => 0,
                'sold_count' => 0,
                'view_count' => 0,
            ],
        );

        foreach ($translations as $tr) {
            ProductTranslation::updateOrCreate(
                ['product_id' => $product->id, 'locale' => $tr['locale']],
                [
                    'name' => $tr['name'],
                    'slug' => $tr['slug'],
                    'short_description' => $tr['short_description'] ?? '',
                    'description' => '',
                    'seo_title' => '',
                    'seo_keywords' => '',
                    'seo_description' => '',
                ],
            );
        }

        ProductVariant::updateOrCreate(
            ['product_id' => $product->id, 'sku' => $variantSku],
            [
                'barcode' => '',
                'price' => $basePrice,
                'compare_at_price' => null,
                'cost' => 0,
                'weight' => 0,
                'weight_unit' => 'g',
                'dimensions' => '',
                'stock' => $stock,
                'reserved' => 0,
                'low_stock_threshold' => 0,
                'image' => '',
                'status' => 1,
                'sort' => 0,
            ],
        );
    }
}

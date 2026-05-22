<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Category;
use App\Models\Mall\CategoryTranslation;
use App\Models\Mall\Product;
use App\Models\Mall\ProductTranslation;
use App\Models\Shop;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 商城前台公开接口（M11-PR43）：categories + products list/show。
 *
 * 共同前提：shop 中间件按 X-Shop-Subdomain header 或 host 子域解析 tenant + shop。
 */
class ShopCatalogPublicTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mall.platform_domain', 'platform.local');
        config()->set('mall.shop_header', 'X-Shop-Subdomain');

        $this->tenant = Tenant::create([
            'name' => 'Demo Tenant',
            'code' => 'TEN_'.uniqid(),
            'status' => 1,
        ]);
        $this->shop = Shop::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Demo Shop',
            'code' => 'SHOP_'.uniqid(),
            'subdomain' => 'demo-shop',
            'locale' => 'zh-CN',
            'currency' => 'CNY',
            'status' => 1,
        ]);
    }

    private function shopHeaders(array $extra = []): array
    {
        return array_merge(['X-Shop-Subdomain' => $this->shop->subdomain], $extra);
    }

    private function makeCategory(array $attrs = [], array $translations = []): Category
    {
        $cat = Category::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'parent_id' => 0,
            'code' => 'CAT_'.uniqid(),
            'sort' => 0,
            'status' => 1,
        ], $attrs));

        foreach ($translations as $t) {
            CategoryTranslation::create(array_merge(['category_id' => $cat->id], $t));
        }

        return $cat;
    }

    private function makeProduct(array $attrs = [], array $translations = []): Product
    {
        $product = Product::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'shop_id' => $this->shop->id,
            'sku_prefix' => 'SP-'.uniqid(),
            'base_price' => 99.99,
            'base_currency' => 'CNY',
            'status' => 1,
            'sort' => 0,
        ], $attrs));

        foreach ($translations as $t) {
            ProductTranslation::create(array_merge(['product_id' => $product->id], $t));
        }

        return $product;
    }

    // === Categories ===

    public function test_categories_index_returns_only_active_for_current_tenant(): void
    {
        $a = $this->makeCategory(['code' => 'A'], [['locale' => 'zh-CN', 'name' => '类目A']]);
        $b = $this->makeCategory(['code' => 'B'], [['locale' => 'zh-CN', 'name' => '类目B']]);
        $this->makeCategory(['code' => 'OFF', 'status' => 0]); // 禁用，不应返回

        // 别的租户不应漏出
        $other = Tenant::create(['name' => 'X', 'code' => 'OTHER', 'status' => 1]);
        Shop::create([
            'tenant_id' => $other->id,
            'name' => 'X', 'code' => 'X', 'subdomain' => 'other-shop', 'status' => 1,
        ]);
        Category::create(['tenant_id' => $other->id, 'code' => 'OTHER', 'status' => 1]);

        $response = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/categories');

        $response->assertOk();
        $items = $response->json('data');
        $codes = array_column($items, 'code');
        $this->assertEqualsCanonicalizing([$a->code, $b->code], $codes);
    }

    public function test_categories_resource_resolves_translation_by_x_locale_header(): void
    {
        $cat = $this->makeCategory(['code' => 'C-MULTI'], [
            ['locale' => 'zh-CN', 'name' => '中文名'],
            ['locale' => 'en', 'name' => 'English Name'],
        ]);

        $en = $this->withHeaders($this->shopHeaders(['X-Locale' => 'en']))
            ->getJson('/api/v1/shop/categories');
        $en->assertOk();
        $row = collect($en->json('data'))->firstWhere('id', $cat->id);
        $this->assertSame('English Name', $row['name']);

        $zh = $this->withHeaders($this->shopHeaders(['X-Locale' => 'zh-CN']))
            ->getJson('/api/v1/shop/categories');
        $zhRow = collect($zh->json('data'))->firstWhere('id', $cat->id);
        $this->assertSame('中文名', $zhRow['name']);
    }

    public function test_categories_resource_falls_back_to_first_translation_when_locale_misses(): void
    {
        $cat = $this->makeCategory(['code' => 'C-FALLBACK'], [
            ['locale' => 'zh-CN', 'name' => '默认中文'],
        ]);

        $response = $this->withHeaders($this->shopHeaders(['X-Locale' => 'fr']))
            ->getJson('/api/v1/shop/categories')
            ->json('data');

        $row = collect($response)->firstWhere('id', $cat->id);
        $this->assertSame('默认中文', $row['name']);
    }

    // === Products ===

    public function test_products_index_returns_only_active_for_current_tenant(): void
    {
        $p1 = $this->makeProduct([], [['locale' => 'zh-CN', 'name' => '商品 1', 'slug' => 'product-1']]);
        $this->makeProduct(['status' => 0], [['locale' => 'zh-CN', 'name' => '草稿', 'slug' => 'draft']]);

        // 别的租户的商品
        $other = Tenant::create(['name' => 'O', 'code' => 'O_'.uniqid(), 'status' => 1]);
        Product::create([
            'tenant_id' => $other->id,
            'sku_prefix' => 'X', 'base_price' => 1, 'base_currency' => 'CNY', 'status' => 1,
        ]);

        $response = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertSame($p1->id, $list[0]['id']);
        $this->assertSame('商品 1', $list[0]['name']);
        $this->assertSame('product-1', $list[0]['slug']);
    }

    public function test_products_index_includes_tenant_level_shared_product(): void
    {
        // shop_id null → 租户级共享，应被当前 shop 列出
        $shared = $this->makeProduct(['shop_id' => null], [['locale' => 'zh-CN', 'name' => '共享商品']]);
        $own = $this->makeProduct([], [['locale' => 'zh-CN', 'name' => '本店商品']]);

        $list = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products')
            ->json('data.list');

        $ids = array_column($list, 'id');
        $this->assertEqualsCanonicalizing([$shared->id, $own->id], $ids);
    }

    public function test_products_index_excludes_other_shop_in_same_tenant(): void
    {
        $otherShop = Shop::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other', 'code' => 'O', 'subdomain' => 'other-in-tenant', 'status' => 1,
        ]);
        $this->makeProduct(['shop_id' => $otherShop->id], [['locale' => 'zh-CN', 'name' => '别店商品']]);
        $own = $this->makeProduct([], [['locale' => 'zh-CN', 'name' => '本店商品']]);

        $list = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products')
            ->json('data.list');

        $ids = array_column($list, 'id');
        $this->assertSame([$own->id], $ids);
    }

    public function test_products_index_filters_by_category(): void
    {
        $catA = $this->makeCategory(['code' => 'A'], [['locale' => 'zh-CN', 'name' => 'A']]);
        $catB = $this->makeCategory(['code' => 'B'], [['locale' => 'zh-CN', 'name' => 'B']]);
        $a = $this->makeProduct(['category_id' => $catA->id], [['locale' => 'zh-CN', 'name' => 'A 商品']]);
        $this->makeProduct(['category_id' => $catB->id], [['locale' => 'zh-CN', 'name' => 'B 商品']]);

        $list = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products?category_id='.$catA->id)
            ->json('data.list');

        $this->assertCount(1, $list);
        $this->assertSame($a->id, $list[0]['id']);
    }

    public function test_products_index_filters_by_keywords(): void
    {
        $hit = $this->makeProduct([], [['locale' => 'zh-CN', 'name' => '运动鞋款']]);
        $this->makeProduct([], [['locale' => 'zh-CN', 'name' => '保温杯']]);

        $list = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products?keywords=运动')
            ->json('data.list');

        $this->assertCount(1, $list);
        $this->assertSame($hit->id, $list[0]['id']);
    }

    public function test_products_index_pagination(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeProduct([], [['locale' => 'zh-CN', 'name' => "商品 {$i}", 'slug' => "p-{$i}"]]);
        }

        $response = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products?pageNum=1&pageSize=10')
            ->json('data');

        $this->assertCount(10, $response['list']);
        $this->assertSame(25, $response['total']);
    }

    public function test_products_show_returns_active_product(): void
    {
        $p = $this->makeProduct([], [['locale' => 'zh-CN', 'name' => '细节页商品', 'slug' => 'detail']]);

        $response = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/'.$p->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.name', '细节页商品')
            ->assertJsonPath('data.slug', 'detail');
    }

    public function test_products_show_404_for_inactive_product(): void
    {
        $p = $this->makeProduct(['status' => 0], [['locale' => 'zh-CN', 'name' => '草稿']]);

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/'.$p->id)
            ->assertStatus(404);
    }

    public function test_products_show_404_for_other_tenant(): void
    {
        $other = Tenant::create(['name' => 'O', 'code' => 'O_'.uniqid(), 'status' => 1]);
        $foreign = Product::create([
            'tenant_id' => $other->id,
            'sku_prefix' => 'F', 'base_price' => 1, 'base_currency' => 'CNY', 'status' => 1,
        ]);

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/'.$foreign->id)
            ->assertStatus(404);
    }

    public function test_products_show_404_for_other_shop_in_same_tenant(): void
    {
        $other = Shop::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'O', 'code' => 'O', 'subdomain' => 'other-tenant', 'status' => 1,
        ]);
        $foreign = $this->makeProduct(['shop_id' => $other->id], [['locale' => 'zh-CN', 'name' => '别店']]);

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/'.$foreign->id)
            ->assertStatus(404);
    }

    public function test_products_index_resource_resolves_locale(): void
    {
        $p = $this->makeProduct([], [
            ['locale' => 'zh-CN', 'name' => '中文名', 'slug' => 'zh-slug'],
            ['locale' => 'en', 'name' => 'English', 'slug' => 'en-slug'],
        ]);

        $list = $this->withHeaders($this->shopHeaders(['X-Locale' => 'en']))
            ->getJson('/api/v1/shop/products')
            ->json('data.list');

        $row = collect($list)->firstWhere('id', $p->id);
        $this->assertSame('English', $row['name']);
        $this->assertSame('en-slug', $row['slug']);
    }

    public function test_endpoint_returns_400_when_shop_not_resolved(): void
    {
        $this->getJson('/api/v1/shop/products')->assertStatus(400);
        $this->getJson('/api/v1/shop/categories')->assertStatus(400);
    }

    // === Show by slug (M11-PR44) ===

    public function test_show_by_slug_returns_product(): void
    {
        $p = $this->makeProduct([], [
            ['locale' => 'zh-CN', 'name' => '红帽子', 'slug' => 'red-hat'],
        ]);

        $response = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/by-slug/red-hat');

        $response->assertOk()
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.slug', 'red-hat');
    }

    public function test_show_by_slug_prefers_current_locale(): void
    {
        $p = $this->makeProduct([], [
            ['locale' => 'zh-CN', 'name' => '帽子', 'slug' => 'hat'],
            ['locale' => 'en', 'name' => 'Hat', 'slug' => 'hat'],
        ]);

        $en = $this->withHeaders($this->shopHeaders(['X-Locale' => 'en']))
            ->getJson('/api/v1/shop/products/by-slug/hat');

        $en->assertOk()
            ->assertJsonPath('data.id', $p->id)
            ->assertJsonPath('data.name', 'Hat');
    }

    public function test_show_by_slug_falls_back_when_locale_misses(): void
    {
        $p = $this->makeProduct([], [
            ['locale' => 'zh-CN', 'name' => '中文标题', 'slug' => 'zh-only'],
        ]);

        $response = $this->withHeaders($this->shopHeaders(['X-Locale' => 'fr']))
            ->getJson('/api/v1/shop/products/by-slug/zh-only');

        $response->assertOk()->assertJsonPath('data.id', $p->id);
    }

    public function test_show_by_slug_404_when_unknown_slug(): void
    {
        $this->makeProduct([], [['locale' => 'zh-CN', 'name' => 'x', 'slug' => 'real']]);

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/by-slug/ghost')
            ->assertStatus(404);
    }

    public function test_show_by_slug_404_for_other_tenant_product(): void
    {
        $other = Tenant::create(['name' => 'O', 'code' => 'O_'.uniqid(), 'status' => 1]);
        $foreign = Product::create([
            'tenant_id' => $other->id,
            'sku_prefix' => 'F', 'base_price' => 1, 'base_currency' => 'CNY', 'status' => 1,
        ]);
        ProductTranslation::create([
            'product_id' => $foreign->id,
            'locale' => 'zh-CN',
            'name' => 'foreign',
            'slug' => 'foreign-slug',
        ]);

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/by-slug/foreign-slug')
            ->assertStatus(404);
    }

    public function test_show_by_slug_404_when_status_inactive(): void
    {
        $this->makeProduct(['status' => 0], [['locale' => 'zh-CN', 'name' => '草稿', 'slug' => 'draft']]);

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/by-slug/draft')
            ->assertStatus(404);
    }

    public function test_show_by_slug_works_for_tenant_level_shared_product(): void
    {
        $shared = $this->makeProduct(
            ['shop_id' => null],
            [['locale' => 'zh-CN', 'name' => '共享', 'slug' => 'shared']],
        );

        $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products/by-slug/shared')
            ->assertOk()
            ->assertJsonPath('data.id', $shared->id);
    }

    public function test_pagesize_is_capped_at_60(): void
    {
        for ($i = 0; $i < 80; $i++) {
            $this->makeProduct([], [['locale' => 'zh-CN', 'name' => "p{$i}", 'slug' => "s{$i}"]]);
        }

        $response = $this->withHeaders($this->shopHeaders())
            ->getJson('/api/v1/shop/products?pageSize=200')
            ->json('data');

        $this->assertSame(60, $response['pageSize']);
        $this->assertCount(60, $response['list']);
    }
}

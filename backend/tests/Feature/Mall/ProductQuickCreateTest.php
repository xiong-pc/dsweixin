<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_create_builds_product_and_default_variant_in_one_request(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '简易商品', 'slug' => 'simple-001'],
                ['locale' => 'en-US', 'name' => 'Simple Product', 'slug' => 'simple-001-en'],
            ],
            'sku' => 'SIMPLE-001',
            'price' => 99.99,
            'stock' => 50,
            'cover_image' => 'https://cdn.example.com/cover.jpg',
            'status' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.base_price', '99.99')
            ->assertJsonPath('data.status', 1);

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenantId,
            'base_price' => 99.99,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('product_translations', ['locale' => 'zh-CN', 'name' => '简易商品']);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SIMPLE-001',
            'price' => 99.99,
            'stock' => 50,
        ]);
    }

    public function test_quick_create_sku_must_be_unique(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $product = Product::create(['tenant_id' => $tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '已有']);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'TAKEN-SKU', 'price' => 100]);

        $response = $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => '新建']],
            'sku' => 'TAKEN-SKU',
            'price' => 50,
            'stock' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_quick_create_requires_price_and_stock(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        // 缺 price 应 422
        $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => '缺 price']],
            'sku' => 'NO-PRICE',
            'stock' => 10,
        ])->assertStatus(422);

        // 缺 stock 应 422
        $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => '缺 stock']],
            'sku' => 'NO-STOCK',
            'price' => 99,
        ])->assertStatus(422);
    }

    public function test_quick_create_validates_slug_uniqueness(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        // 已有产品占用 slug
        $existing = Product::create(['tenant_id' => $tenantId, 'shop_id' => null]);
        $existing->translations()->create(['locale' => 'zh-CN', 'name' => '已有', 'slug' => 'taken-slug']);

        $response = $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '新建', 'slug' => 'taken-slug'],
            ],
            'sku' => 'NEW-001',
            'price' => 50,
            'stock' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_quick_create_uses_price_as_spu_base_price(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => '基础价测试']],
            'sku' => 'BP-001',
            'price' => 88.88,
            'stock' => 10,
            'base_currency' => 'USD',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.base_price', '88.88')
            ->assertJsonPath('data.base_currency', 'USD');
    }

    public function test_quick_create_persists_optional_weight_and_compare_at_price(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => '可选字段']],
            'sku' => 'OPT-001',
            'price' => 50,
            'stock' => 10,
            'compare_at_price' => 80,
            'weight' => 250.5,
            'weight_unit' => 'g',
        ]);

        $response->assertOk();

        $variant = ProductVariant::where('sku', 'OPT-001')->firstOrFail();
        $this->assertSame('80.00', (string) $variant->compare_at_price);
        $this->assertSame('250.500', (string) $variant->weight);
        $this->assertSame('g', $variant->weight_unit);
    }

    public function test_unauthenticated_cannot_quick_create(): void
    {
        $response = $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
            'sku' => 'X-001',
            'price' => 10,
            'stock' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_quick_create_is_atomic_no_orphan_product_on_sku_failure(): void
    {
        // 模拟 SKU 重复时，product 不应被持久化（事务）
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $countBefore = Product::where('tenant_id', $tenantId)->count();

        // 占用 SKU
        $existing = Product::create(['tenant_id' => $tenantId]);
        $existing->translations()->create(['locale' => 'zh-CN', 'name' => '已有']);
        ProductVariant::create(['product_id' => $existing->id, 'sku' => 'ATOMIC', 'price' => 10]);

        $this->postJson('/api/v1/mall/products/quick-create', [
            'translations' => [['locale' => 'zh-CN', 'name' => '尝试新建']],
            'sku' => 'ATOMIC',
            'price' => 50,
            'stock' => 10,
        ])->assertStatus(422);

        // 产品数应该不变（除了我们 setUp 时建的 existing）
        $this->assertSame($countBefore + 1, Product::where('tenant_id', $tenantId)->count());
        $this->assertDatabaseMissing('product_translations', ['name' => '尝试新建']);
    }
}

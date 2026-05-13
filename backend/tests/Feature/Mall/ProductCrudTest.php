<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $code): Tenant
    {
        return Tenant::create([
            'code' => $code,
            'name' => strtoupper($code),
            'status' => 1,
            'primary_domain' => "{$code}.example.com",
        ]);
    }

    public function test_admin_can_create_product_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products', [
            'sku_prefix' => 'TEST-001',
            'base_price' => 199.99,
            'base_currency' => 'CNY',
            'cover_image' => 'https://cdn.example.com/cover.jpg',
            'images' => ['https://cdn.example.com/1.jpg', 'https://cdn.example.com/2.jpg'],
            'status' => 1,
            'translations' => [
                [
                    'locale' => 'zh-CN',
                    'name' => '测试商品',
                    'slug' => 'test-product',
                    'short_description' => '简短描述',
                    'description' => '<p>详细描述</p>',
                    'seo_title' => '测试商品 SEO',
                    'seo_keywords' => '测试,商品',
                    'seo_description' => '测试商品的 SEO 描述',
                ],
                [
                    'locale' => 'en-US',
                    'name' => 'Test Product',
                    'slug' => 'test-product-en',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sku_prefix', 'TEST-001')
            ->assertJsonPath('data.base_price', '199.99')
            ->assertJsonPath('data.base_currency', 'CNY')
            ->assertJsonCount(2, 'data.translations')
            ->assertJsonCount(2, 'data.images');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('products', ['tenant_id' => $tenantId, 'sku_prefix' => 'TEST-001']);
        $this->assertDatabaseHas('product_translations', ['locale' => 'zh-CN', 'name' => '测试商品']);
        $this->assertDatabaseHas('product_translations', ['locale' => 'en-US', 'name' => 'Test Product']);
    }

    public function test_create_product_requires_at_least_one_translation(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products', [
            'sku_prefix' => 'TEST-001',
            'translations' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_product_validates_slug_format(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products', [
            'translations' => [
                [
                    'locale' => 'zh-CN',
                    'name' => '商品',
                    'slug' => 'Bad Slug',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_create_product_with_shop_id_null_means_shared(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products', [
            'shop_id' => null,
            'translations' => [['locale' => 'zh-CN', 'name' => '共享商品']],
        ]);

        $response->assertOk()->assertJsonPath('data.shop_id', null);
    }

    public function test_admin_can_update_product_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = Product::create(['tenant_id' => $tenantId, 'sku_prefix' => 'P1']);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '旧名']);

        $response = $this->putJson("/api/v1/mall/products/{$product->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '新名'],
                ['locale' => 'en-US', 'name' => 'New Name'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_translations', ['locale' => 'zh-CN', 'name' => '新名']);
        $this->assertDatabaseHas('product_translations', ['locale' => 'en-US', 'name' => 'New Name']);
        $this->assertDatabaseMissing('product_translations', ['locale' => 'zh-CN', 'name' => '旧名']);
    }

    public function test_admin_can_filter_products_by_keywords(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $p1 = Product::create(['tenant_id' => $tenantId, 'sku_prefix' => 'TSHIRT-001']);
        $p1->translations()->create(['locale' => 'zh-CN', 'name' => '夏季T恤']);

        $p2 = Product::create(['tenant_id' => $tenantId, 'sku_prefix' => 'PANTS-001']);
        $p2->translations()->create(['locale' => 'zh-CN', 'name' => '裤子']);

        $response = $this->getJson('/api/v1/mall/products?keywords=T恤');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
    }

    public function test_admin_can_filter_products_by_status(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        Product::create(['tenant_id' => $tenantId, 'status' => 1]);
        Product::create(['tenant_id' => $tenantId, 'status' => 0]);

        $response = $this->getJson('/api/v1/mall/products?status=1');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $product = Product::create(['tenant_id' => $tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'X']);

        $response = $this->deleteJson("/api/v1/mall/products/{$product->id}");

        $response->assertOk();
        // 软删除：products 表里 deleted_at 已设
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        // 翻译表硬删
        $this->assertDatabaseMissing('product_translations', ['product_id' => $product->id]);
    }

    public function test_unauthenticated_cannot_access_products(): void
    {
        $this->getJson('/api/v1/mall/products')->assertStatus(401);
    }

    public function test_create_product_persists_json_images_field(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $images = ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.jpg'];
        $response = $this->postJson('/api/v1/mall/products', [
            'images' => $images,
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ]);

        $response->assertOk();
        $product = Product::first();
        $this->assertSame($images, $product->images);
    }
}

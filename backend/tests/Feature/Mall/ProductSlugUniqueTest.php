<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSlugUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_must_be_unique_within_same_shop_and_locale(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $existing = Product::create(['tenant_id' => $tenantId, 'shop_id' => 1]);
        $existing->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'pro']);

        $response = $this->postJson('/api/v1/mall/products', [
            'shop_id' => 1,
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'B', 'slug' => 'pro'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_same_slug_allowed_across_different_locales(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $existing = Product::create(['tenant_id' => $tenantId, 'shop_id' => 1]);
        $existing->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'pro']);

        $response = $this->postJson('/api/v1/mall/products', [
            'shop_id' => 1,
            'translations' => [
                ['locale' => 'en-US', 'name' => 'B', 'slug' => 'pro'],
            ],
        ]);

        $response->assertOk();
    }

    public function test_same_slug_allowed_across_different_shops(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $existing = Product::create(['tenant_id' => $tenantId, 'shop_id' => 1]);
        $existing->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'pro']);

        $response = $this->postJson('/api/v1/mall/products', [
            'shop_id' => 2,
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'B', 'slug' => 'pro'],
            ],
        ]);

        $response->assertOk();
    }

    public function test_shop_null_products_share_slug_namespace(): void
    {
        // shop_id NULL 表示租户全店铺共享商品，所有 NULL shop_id 商品共享 slug 命名空间
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $existing = Product::create(['tenant_id' => $tenantId, 'shop_id' => null]);
        $existing->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'shared']);

        $response = $this->postJson('/api/v1/mall/products', [
            'shop_id' => null,
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'B', 'slug' => 'shared'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_update_allows_keeping_own_slug(): void
    {
        // 更新自己的商品，保留同样的 slug 应允许
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $product = Product::create(['tenant_id' => $tenantId, 'shop_id' => 1]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'pro']);

        $response = $this->putJson("/api/v1/mall/products/{$product->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'A updated', 'slug' => 'pro'],
            ],
        ]);

        $response->assertOk();
    }

    public function test_update_rejects_taking_another_products_slug(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $p1 = Product::create(['tenant_id' => $tenantId, 'shop_id' => 1]);
        $p1->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'first']);

        $p2 = Product::create(['tenant_id' => $tenantId, 'shop_id' => 1]);
        $p2->translations()->create(['locale' => 'zh-CN', 'name' => 'B', 'slug' => 'second']);

        $response = $this->putJson("/api/v1/mall/products/{$p2->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'B updated', 'slug' => 'first'],
            ],
        ]);

        $response->assertStatus(422);
    }
}

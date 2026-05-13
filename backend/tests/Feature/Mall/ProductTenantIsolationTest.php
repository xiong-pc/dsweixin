<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTenantIsolationTest extends TestCase
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

    public function test_admin_cannot_list_other_tenant_products(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other');

        $mine = Product::create(['tenant_id' => $myTenantId]);
        $mine->translations()->create(['locale' => 'zh-CN', 'name' => '我的']);

        $theirs = Product::create(['tenant_id' => $other->id]);
        $theirs->translations()->create(['locale' => 'zh-CN', 'name' => '别人的']);

        $response = $this->getJson('/api/v1/mall/products');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('我的', $response->json('data.list.0.translations.0.name'));
    }

    public function test_admin_cannot_show_other_tenant_product(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $theirs = Product::create(['tenant_id' => $other->id]);

        $response = $this->getJson("/api/v1/mall/products/{$theirs->id}");

        $response->assertStatus(403);
    }

    public function test_admin_cannot_update_other_tenant_product(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $theirs = Product::create(['tenant_id' => $other->id]);

        $response = $this->putJson("/api/v1/mall/products/{$theirs->id}", [
            'status' => 0,
            'translations' => [['locale' => 'zh-CN', 'name' => 'modified']],
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_delete_other_tenant_product(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $theirs = Product::create(['tenant_id' => $other->id]);

        $response = $this->deleteJson("/api/v1/mall/products/{$theirs->id}");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_see_all_tenant_products(): void
    {
        $this->actingAsSuperAdmin();

        $t1 = $this->createTenant('t1');
        $t2 = $this->createTenant('t2');
        Product::create(['tenant_id' => $t1->id]);
        Product::create(['tenant_id' => $t2->id]);

        $response = $this->getJson('/api/v1/mall/products');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_slug_uniqueness_does_not_cross_tenant_boundary(): void
    {
        // 不同租户允许相同 slug
        $t1 = $this->createTenant('t1');
        $p1 = Product::create(['tenant_id' => $t1->id, 'shop_id' => 1]);
        $p1->translations()->create(['locale' => 'zh-CN', 'name' => 'A', 'slug' => 'pro']);

        // 用 default tenant + admin 创建同 slug 应允许（不同 tenant）
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/products', [
            'shop_id' => 1,
            'translations' => [
                ['locale' => 'zh-CN', 'name' => 'B', 'slug' => 'pro'],
            ],
        ]);

        $response->assertOk();
    }
}

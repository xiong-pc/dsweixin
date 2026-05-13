<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\Brand;
use App\Models\Mall\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandCrudTest extends TestCase
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

    public function test_admin_can_create_brand_with_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/brands', [
            'code' => 'nike',
            'logo' => 'https://cdn.example.com/nike.png',
            'website' => 'https://www.nike.com',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '耐克', 'description' => '运动品牌'],
                ['locale' => 'en-US', 'name' => 'Nike', 'description' => 'Sports brand'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'nike')
            ->assertJsonPath('data.logo', 'https://cdn.example.com/nike.png')
            ->assertJsonCount(2, 'data.translations');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('brands', ['tenant_id' => $tenantId, 'code' => 'nike']);
        $this->assertDatabaseHas('brand_translations', [
            'locale' => 'zh-CN',
            'name' => '耐克',
            'description' => '运动品牌',
        ]);
    }

    public function test_admin_can_list_brands_of_own_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $tenantId = (int) auth()->user()->tenant_id;
        Brand::create(['tenant_id' => $tenantId, 'code' => 'a']);
        Brand::create(['tenant_id' => $tenantId, 'code' => 'b']);

        $response = $this->getJson('/api/v1/mall/brands');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_admin_cannot_see_other_tenant_brands(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other');

        Brand::create(['tenant_id' => $myTenantId, 'code' => 'mine']);
        Brand::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $response = $this->getJson('/api/v1/mall/brands');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('mine', $response->json('data.list.0.code'));
    }

    public function test_super_admin_can_see_all_tenant_brands(): void
    {
        $this->actingAsSuperAdmin();

        $t1 = $this->createTenant('t1');
        $t2 = $this->createTenant('t2');
        Brand::create(['tenant_id' => $t1->id, 'code' => 'b1']);
        Brand::create(['tenant_id' => $t2->id, 'code' => 'b2']);

        $response = $this->getJson('/api/v1/mall/brands');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_website_must_be_valid_url(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/brands', [
            'website' => 'not-a-url',
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_update_brand_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $brand = Brand::create(['tenant_id' => $tenantId, 'code' => 'old']);
        $brand->translations()->create(['locale' => 'zh-CN', 'name' => '旧名', 'description' => '']);

        $response = $this->putJson("/api/v1/mall/brands/{$brand->id}", [
            'code' => 'new',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '新名', 'description' => '新描述'],
                ['locale' => 'en-US', 'name' => 'New'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('brand_translations', ['locale' => 'zh-CN', 'name' => '新名', 'description' => '新描述']);
        $this->assertDatabaseHas('brand_translations', ['locale' => 'en-US', 'name' => 'New']);
        $this->assertDatabaseMissing('brand_translations', ['locale' => 'zh-CN', 'name' => '旧名']);
    }

    public function test_admin_cannot_update_other_tenant_brand(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $brand = Brand::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $response = $this->putJson("/api/v1/mall/brands/{$brand->id}", [
            'status' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_brand_with_associated_products(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $brand = Brand::create(['tenant_id' => $tenantId, 'code' => 'nike']);
        Product::create(['tenant_id' => $tenantId, 'brand_id' => $brand->id]);

        $response = $this->deleteJson("/api/v1/mall/brands/{$brand->id}");

        $response->assertStatus(400);
    }

    public function test_admin_can_soft_delete_brand_without_products(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $brand = Brand::create(['tenant_id' => $tenantId, 'code' => 'lonely']);
        $brand->translations()->create(['locale' => 'zh-CN', 'name' => '孤独', 'description' => '']);

        $response = $this->deleteJson("/api/v1/mall/brands/{$brand->id}");

        $response->assertOk();
        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
        $this->assertDatabaseMissing('brand_translations', ['brand_id' => $brand->id]);
    }

    public function test_keywords_filter_searches_name_in_translations(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $b1 = Brand::create(['tenant_id' => $tenantId, 'code' => 'nike']);
        $b1->translations()->create(['locale' => 'zh-CN', 'name' => '耐克', 'description' => '']);

        $b2 = Brand::create(['tenant_id' => $tenantId, 'code' => 'adidas']);
        $b2->translations()->create(['locale' => 'zh-CN', 'name' => '阿迪', 'description' => '']);

        $response = $this->getJson('/api/v1/mall/brands?keywords=耐克');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
    }

    public function test_unauthenticated_cannot_access_brands(): void
    {
        $this->getJson('/api/v1/mall/brands')->assertStatus(401);
    }

    public function test_code_format_must_be_lowercase_snake(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/brands', [
            'code' => 'BadCode',
            'translations' => [['locale' => 'zh-CN', 'name' => 'X']],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_filter_brands_by_status(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        Brand::create(['tenant_id' => $tenantId, 'code' => 'active', 'status' => 1]);
        Brand::create(['tenant_id' => $tenantId, 'code' => 'inactive', 'status' => 0]);

        $response = $this->getJson('/api/v1/mall/brands?status=1');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function createShop(array $attrs = []): Shop
    {
        $tenant = Tenant::firstOrCreate(
            ['code' => 'DEFAULT'],
            ['id' => 1, 'name' => '默认租户', 'status' => 1]
        );

        return Shop::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => '测试店铺',
            'code' => 'SHOP_'.uniqid(),
            'status' => 1,
        ], $attrs));
    }

    public function test_super_admin_can_list_all_shops(): void
    {
        $this->actingAsSuperAdmin();
        $this->createShop();
        $this->createShop();

        $response = $this->getJson('/api/v1/system/shops');

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['data' => ['list', 'total']]);
    }

    public function test_admin_only_sees_own_tenant_shops(): void
    {
        $this->ensureDefaultTenant();
        $otherTenant = Tenant::create(['name' => '别的租户', 'code' => 'OTHER', 'status' => 1]);

        $this->createShop(['name' => '本租户店铺']);
        Shop::create([
            'tenant_id' => $otherTenant->id,
            'name' => '别租户店铺',
            'code' => 'OTHER_SHOP',
            'status' => 1,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/system/shops');

        $response->assertOk();
        $list = $response->json('data.list');
        $this->assertCount(1, $list);
        $this->assertSame('本租户店铺', $list[0]['name']);
    }

    public function test_admin_can_create_shop_in_own_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/shops', [
            'name' => '新店铺',
            'code' => 'NEW_SHOP',
            'subdomain' => 'newshop',
            'locale' => 'en-US',
            'currency' => 'USD',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.code', 'NEW_SHOP')
            ->assertJsonPath('data.subdomain', 'newshop')
            ->assertJsonPath('data.locale', 'en-US')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.tenant_id', 1);

        $this->assertDatabaseHas('shops', ['code' => 'NEW_SHOP', 'tenant_id' => 1]);
    }

    public function test_super_admin_can_specify_tenant_when_creating(): void
    {
        $otherTenant = Tenant::create(['name' => '别租户', 'code' => 'OTHER_T', 'status' => 1]);
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/shops', [
            'tenant_id' => $otherTenant->id,
            'name' => '超管建的店铺',
            'code' => 'SUPER_SHOP',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('shops', [
            'code' => 'SUPER_SHOP',
            'tenant_id' => $otherTenant->id,
        ]);
    }

    public function test_default_locale_and_currency_when_not_specified(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/shops', [
            'name' => '默认值店铺',
            'code' => 'DEFAULT_SHOP',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.locale', 'zh-CN')
            ->assertJsonPath('data.currency', 'CNY')
            ->assertJsonPath('data.timezone', 'Asia/Shanghai');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/shops', []);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_subdomain_must_be_unique_globally(): void
    {
        $this->ensureDefaultTenant();
        $this->createShop(['subdomain' => 'acme-cn']);

        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/shops', [
            'name' => '重复子域',
            'code' => 'DUP_SUBDOMAIN',
            'subdomain' => 'acme-cn',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_subdomain_format_validation(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/system/shops', [
            'name' => '非法子域',
            'code' => 'BAD_SUB',
            'subdomain' => 'BadSubdomain!',
        ]);

        $response->assertStatus(422);
    }

    public function test_subdomain_unique_across_tenants(): void
    {
        $tenant1 = $this->ensureDefaultTenant();
        $tenant2 = Tenant::create(['name' => '别租户', 'code' => 'OTHER_T2', 'status' => 1]);

        Shop::create([
            'tenant_id' => $tenant1->id,
            'name' => '租户1店铺',
            'code' => 'T1_SHOP',
            'subdomain' => 'shared-sub',
            'status' => 1,
        ]);

        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/system/shops', [
            'tenant_id' => $tenant2->id,
            'name' => '租户2想用同样子域',
            'code' => 'T2_SHOP',
            'subdomain' => 'shared-sub',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_view_shop_detail(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $shop = $this->createShop(['name' => '查看店铺', 'code' => 'VIEW_S']);

        $response = $this->getJson("/api/v1/system/shops/{$shop->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'VIEW_S');
    }

    public function test_admin_cannot_view_other_tenant_shop(): void
    {
        $this->ensureDefaultTenant();
        $otherTenant = Tenant::create(['name' => '别租户', 'code' => 'OTHER_VIEW', 'status' => 1]);

        $otherShop = Shop::create([
            'tenant_id' => $otherTenant->id,
            'name' => '别人店铺',
            'code' => 'OTHER_S',
            'status' => 1,
        ]);

        $this->actingAsAdmin();

        // 由于全局 scope 隔离，路由模型绑定直接 404
        $response = $this->getJson("/api/v1/system/shops/{$otherShop->id}");

        $response->assertStatus(404);
    }

    public function test_admin_can_update_own_shop(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $shop = $this->createShop();

        $response = $this->putJson("/api/v1/system/shops/{$shop->id}", [
            'name' => '修改后名称',
            'locale' => 'ja-JP',
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => '修改后名称',
            'locale' => 'ja-JP',
        ]);
    }

    public function test_admin_can_delete_own_shop(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $shop = $this->createShop();

        $response = $this->deleteJson("/api/v1/system/shops/{$shop->id}");

        $response->assertOk()->assertJsonPath('code', 200);
        $this->assertSoftDeleted('shops', ['id' => $shop->id]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/system/shops');

        $response->assertStatus(401);
    }

    public function test_shop_belongs_to_tenant_relation(): void
    {
        $this->ensureDefaultTenant();
        $shop = $this->createShop();

        $this->assertInstanceOf(Tenant::class, $shop->tenant);
        $this->assertSame(1, $shop->tenant->id);
    }

    public function test_tenant_has_many_shops_relation(): void
    {
        $tenant = $this->ensureDefaultTenant();
        $this->createShop(['name' => '店1', 'code' => 'S1']);
        $this->createShop(['name' => '店2', 'code' => 'S2']);

        $this->assertCount(2, $tenant->fresh()->shops);
    }
}

<?php

namespace Tests\Feature\Mall;

use App\Models\Mall\ShippingMethod;
use App\Models\Tenant;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ShippingMethod CRUD + rates 嵌套保存。
 *
 * 关键点：
 *   - 多租户隔离（admin 仅看自己租户）
 *   - rates 整体替换（update 时如果带 rates 则全删全建）
 *   - rate.zone_id 必须存在
 *   - weight_max == 0 视为无上限（合法）
 *   - tenant + code 组合唯一
 */
class ShippingMethodCrudTest extends TestCase
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

    private function makeZone(string $code = 'EU'): Zone
    {
        return Zone::create(['code' => $code, 'name' => $code]);
    }

    public function test_admin_can_create_shipping_method_with_translations_and_rates(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $zone = $this->makeZone('EU');

        $response = $this->postJson('/api/v1/mall/shipping-methods', [
            'code' => 'standard',
            'carrier' => 'SF',
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '普通快递', 'description' => '3-5 天'],
                ['locale' => 'en-US', 'name' => 'Standard Shipping'],
            ],
            'rates' => [
                ['zone_id' => $zone->id, 'weight_min' => 0, 'weight_max' => 1000, 'price' => 12.00, 'free_threshold' => 199],
                ['zone_id' => $zone->id, 'weight_min' => 1000, 'weight_max' => 0, 'price' => 25.00],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'standard')
            ->assertJsonPath('data.carrier', 'SF')
            ->assertJsonCount(2, 'data.translations')
            ->assertJsonCount(2, 'data.rates');

        $tenantId = (int) auth()->user()->tenant_id;
        $this->assertDatabaseHas('shipping_methods', ['tenant_id' => $tenantId, 'code' => 'standard']);
        $this->assertDatabaseHas('shipping_method_translations', ['locale' => 'zh-CN', 'name' => '普通快递']);
        $this->assertDatabaseHas('shipping_rates', [
            'zone_id' => $zone->id,
            'weight_min' => 0,
            'weight_max' => 1000,
            'price' => 12.00,
            'free_threshold' => 199.00,
        ]);
    }

    public function test_admin_lists_own_tenant_methods_only(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $myTenantId = (int) auth()->user()->tenant_id;
        $other = $this->createTenant('other');

        ShippingMethod::create(['tenant_id' => $myTenantId, 'code' => 'mine']);
        ShippingMethod::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $response = $this->getJson('/api/v1/mall/shipping-methods');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('mine', $response->json('data.list.0.code'));
    }

    public function test_super_admin_sees_all_tenants(): void
    {
        $this->actingAsSuperAdmin();

        $t1 = $this->createTenant('t1');
        $t2 = $this->createTenant('t2');
        ShippingMethod::create(['tenant_id' => $t1->id, 'code' => 's1']);
        ShippingMethod::create(['tenant_id' => $t2->id, 'code' => 's2']);

        $response = $this->getJson('/api/v1/mall/shipping-methods');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.total'));
    }

    public function test_show_returns_method_with_rates(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $zone = $this->makeZone('NA');
        $tenantId = (int) auth()->user()->tenant_id;

        $method = ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'express']);
        $method->rates()->create(['zone_id' => $zone->id, 'weight_min' => 0, 'weight_max' => 500, 'price' => 30]);

        $response = $this->getJson("/api/v1/mall/shipping-methods/{$method->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'express')
            ->assertJsonCount(1, 'data.rates')
            ->assertJsonPath('data.rates.0.zone_id', $zone->id);
    }

    public function test_update_replaces_rates_entirely(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $zoneEu = $this->makeZone('EU');
        $zoneNa = $this->makeZone('NA');

        $method = ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'std']);
        $method->rates()->create(['zone_id' => $zoneEu->id, 'weight_min' => 0, 'weight_max' => 1000, 'price' => 10]);

        $response = $this->putJson("/api/v1/mall/shipping-methods/{$method->id}", [
            'rates' => [
                ['zone_id' => $zoneNa->id, 'weight_min' => 0, 'weight_max' => 500, 'price' => 20],
                ['zone_id' => $zoneNa->id, 'weight_min' => 500, 'weight_max' => 0, 'price' => 40],
            ],
        ]);

        $response->assertOk();
        $this->assertSame(0, $method->rates()->where('zone_id', $zoneEu->id)->count());
        $this->assertSame(2, $method->rates()->where('zone_id', $zoneNa->id)->count());
    }

    public function test_update_translations_replaces_set(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $method = ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'std']);
        $method->translations()->create(['locale' => 'zh-CN', 'name' => '旧名']);

        $this->putJson("/api/v1/mall/shipping-methods/{$method->id}", [
            'translations' => [
                ['locale' => 'zh-CN', 'name' => '新名'],
                ['locale' => 'en-US', 'name' => 'New'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('shipping_method_translations', ['locale' => 'zh-CN', 'name' => '新名']);
        $this->assertDatabaseHas('shipping_method_translations', ['locale' => 'en-US', 'name' => 'New']);
        $this->assertDatabaseMissing('shipping_method_translations', ['locale' => 'zh-CN', 'name' => '旧名']);
    }

    public function test_admin_cannot_access_other_tenant_method(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $other = $this->createTenant('other');
        $method = ShippingMethod::create(['tenant_id' => $other->id, 'code' => 'theirs']);

        $this->getJson("/api/v1/mall/shipping-methods/{$method->id}")->assertStatus(403);
        $this->putJson("/api/v1/mall/shipping-methods/{$method->id}", ['status' => 0])->assertStatus(403);
        $this->deleteJson("/api/v1/mall/shipping-methods/{$method->id}")->assertStatus(403);
    }

    public function test_code_must_be_lowercase_snake(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/shipping-methods', [
            'code' => 'BadCode',
            'translations' => [['locale' => 'zh-CN', 'name' => '快递']],
        ]);

        $response->assertStatus(422);
    }

    public function test_code_unique_per_tenant(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'express']);

        // 数据库 unique 索引：同租户 + code 重复会被全局 handler 捕住并返回 500
        $response = $this->postJson('/api/v1/mall/shipping-methods', [
            'code' => 'express',
            'translations' => [['locale' => 'zh-CN', 'name' => '快递']],
        ]);

        $response->assertStatus(500);
        // 确保没有重复入库
        $this->assertSame(1, ShippingMethod::where('tenant_id', $tenantId)->where('code', 'express')->count());
    }

    public function test_rate_zone_must_exist(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/mall/shipping-methods', [
            'code' => 'std',
            'translations' => [['locale' => 'zh-CN', 'name' => 'x']],
            'rates' => [
                ['zone_id' => 99999, 'price' => 10],
            ],
        ]);

        // BusinessException(api.shipping_rate_zone_not_found) → 400
        $response->assertStatus(400);
        // 事务全部回滚，不应写入 method
        $this->assertDatabaseMissing('shipping_methods', ['code' => 'std']);
    }

    public function test_rate_weight_max_must_be_greater_than_min(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $zone = $this->makeZone('EU');

        $response = $this->postJson('/api/v1/mall/shipping-methods', [
            'code' => 'std',
            'translations' => [['locale' => 'zh-CN', 'name' => 'x']],
            'rates' => [
                ['zone_id' => $zone->id, 'weight_min' => 1000, 'weight_max' => 500, 'price' => 10],
            ],
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseMissing('shipping_methods', ['code' => 'std']);
        $this->assertDatabaseMissing('shipping_rates', ['zone_id' => $zone->id]);
    }

    public function test_rate_weight_max_zero_is_treated_as_unlimited(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $zone = $this->makeZone('EU');

        $response = $this->postJson('/api/v1/mall/shipping-methods', [
            'code' => 'std',
            'translations' => [['locale' => 'zh-CN', 'name' => 'x']],
            'rates' => [
                ['zone_id' => $zone->id, 'weight_min' => 5000, 'weight_max' => 0, 'price' => 99],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('shipping_rates', [
            'zone_id' => $zone->id,
            'weight_min' => 5000,
            'weight_max' => 0,
            'price' => 99.00,
        ]);
    }

    public function test_destroy_soft_deletes_method_and_clears_translations_and_rates(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;
        $zone = $this->makeZone('EU');

        $method = ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'tbd']);
        $method->translations()->create(['locale' => 'zh-CN', 'name' => '快递']);
        $method->rates()->create(['zone_id' => $zone->id, 'weight_min' => 0, 'weight_max' => 0, 'price' => 10]);

        $this->deleteJson("/api/v1/mall/shipping-methods/{$method->id}")->assertOk();

        $this->assertSoftDeleted('shipping_methods', ['id' => $method->id]);
        $this->assertDatabaseMissing('shipping_method_translations', ['shipping_method_id' => $method->id]);
        $this->assertDatabaseMissing('shipping_rates', ['shipping_method_id' => $method->id]);
    }

    public function test_keywords_filter_searches_translations_and_carrier(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        $m1 = ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'm1', 'carrier' => 'SF']);
        $m1->translations()->create(['locale' => 'zh-CN', 'name' => '顺丰快递']);

        $m2 = ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'm2', 'carrier' => 'JD']);
        $m2->translations()->create(['locale' => 'zh-CN', 'name' => '京东快递']);

        $this->assertSame(1, $this->getJson('/api/v1/mall/shipping-methods?keywords=顺丰')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/v1/mall/shipping-methods?keywords=SF')->json('data.total'));
    }

    public function test_filter_by_status(): void
    {
        $this->ensureDefaultTenant();
        $this->actingAsAdmin();
        $tenantId = (int) auth()->user()->tenant_id;

        ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'on', 'status' => 1]);
        ShippingMethod::create(['tenant_id' => $tenantId, 'code' => 'off', 'status' => 0]);

        $response = $this->getJson('/api/v1/mall/shipping-methods?status=0');
        $response->assertOk();
        $this->assertSame(1, $response->json('data.total'));
        $this->assertSame('off', $response->json('data.list.0.code'));
    }

    public function test_unauthenticated_rejected(): void
    {
        $this->getJson('/api/v1/mall/shipping-methods')->assertStatus(401);
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\Shop;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/shop/config（M11-PR42）：Nuxt SSR 启动期拉取的店铺信息。
 *
 * 依赖 ShopResolverMiddleware（PR3 已建）按 X-Shop-Subdomain header 或 host 子域解析，
 * 此处仅断言响应字段对齐 + 错误分支正确。
 */
class ShopConfigEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mall.platform_domain', 'platform.local');
        config()->set('mall.reserved_subdomains', ['www', 'api', 'admin']);
        config()->set('mall.shop_header', 'X-Shop-Subdomain');
    }

    private function createTenantWithShop(array $shopOverrides = []): array
    {
        $tenant = Tenant::create([
            'name' => '示例租户',
            'code' => 'TEN_'.uniqid(),
            'status' => 1,
        ]);
        $shop = Shop::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => '示例店铺',
            'code' => 'SHOP_'.uniqid(),
            'subdomain' => 'demo-shop',
            'locale' => 'zh-CN',
            'currency' => 'CNY',
            'timezone' => 'Asia/Shanghai',
            'theme_id' => 0,
            'status' => 1,
        ], $shopOverrides));

        return [$tenant, $shop];
    }

    public function test_returns_shop_config_resolved_via_header(): void
    {
        [$tenant, $shop] = $this->createTenantWithShop([
            'subdomain' => 'acme-jp',
            'locale' => 'ja',
            'currency' => 'JPY',
            'timezone' => 'Asia/Tokyo',
        ]);

        $response = $this->withHeaders(['X-Shop-Subdomain' => 'acme-jp'])
            ->getJson('/api/v1/shop/config');

        $response->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.shop_id', $shop->id)
            ->assertJsonPath('data.subdomain', 'acme-jp')
            ->assertJsonPath('data.locale', 'ja')
            ->assertJsonPath('data.currency', 'JPY')
            ->assertJsonPath('data.timezone', 'Asia/Tokyo');
    }

    public function test_returns_shop_config_resolved_via_host_subdomain(): void
    {
        [, $shop] = $this->createTenantWithShop(['subdomain' => 'host-shop']);

        $response = $this->getJson('http://host-shop.platform.local/api/v1/shop/config');

        $response->assertOk()
            ->assertJsonPath('data.shop_id', $shop->id)
            ->assertJsonPath('data.subdomain', 'host-shop');
    }

    public function test_returns_400_when_no_subdomain_can_be_resolved(): void
    {
        $this->createTenantWithShop();

        // 无 header + host 不是 .platform.local 子域 → ShopResolverMiddleware 返回 400
        $this->getJson('http://platform.local/api/v1/shop/config')
            ->assertStatus(400);
    }

    public function test_returns_404_when_subdomain_does_not_match_any_shop(): void
    {
        $this->createTenantWithShop(['subdomain' => 'real-shop']);

        $this->withHeaders(['X-Shop-Subdomain' => 'ghost-shop'])
            ->getJson('/api/v1/shop/config')
            ->assertStatus(404);
    }

    public function test_returns_403_when_tenant_disabled(): void
    {
        $tenant = Tenant::create([
            'name' => 'disabled',
            'code' => 'TEN_'.uniqid(),
            'status' => 0, // 禁用
        ]);
        Shop::create([
            'tenant_id' => $tenant->id,
            'name' => 's', 'code' => 'SHOP_'.uniqid(),
            'subdomain' => 'disabled-shop', 'status' => 1,
        ]);

        $this->withHeaders(['X-Shop-Subdomain' => 'disabled-shop'])
            ->getJson('/api/v1/shop/config')
            ->assertStatus(403);
    }

    public function test_response_only_exposes_safe_fields(): void
    {
        $this->createTenantWithShop(['subdomain' => 'safe-shop']);

        $data = $this->withHeaders(['X-Shop-Subdomain' => 'safe-shop'])
            ->getJson('/api/v1/shop/config')
            ->json('data');

        // 白名单字段，不应泄露 remark / created_at / updated_at 等内部字段
        $this->assertEqualsCanonicalizing([
            'tenant_id', 'shop_id', 'name', 'code', 'subdomain',
            'locale', 'currency', 'timezone', 'theme_id', 'status',
        ], array_keys($data));
    }
}

<?php

namespace Tests\Feature\Middleware;

use App\Models\Shop;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ShopResolverMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mall.platform_domain', 'platform.local');
        config()->set('mall.reserved_subdomains', ['www', 'api', 'admin']);
        config()->set('mall.shop_header', 'X-Shop-Subdomain');

        Route::middleware('shop')->get('/test/shop-context', function (Request $request) {
            $shop = $request->attributes->get('shop');
            $tenant = $request->attributes->get('tenant');

            return response()->json([
                'code' => 200,
                'shop_id' => $shop?->id,
                'shop_subdomain' => $shop?->subdomain,
                'tenant_id' => $tenant?->id,
            ]);
        });
    }

    private function urlFor(string $host): string
    {
        return 'http://'.$host.'/test/shop-context';
    }

    private function createTenantAndShop(array $tenantAttrs = [], array $shopAttrs = []): array
    {
        $tenant = Tenant::create(array_merge([
            'name' => '测试租户',
            'code' => 'TENANT_'.uniqid(),
            'status' => 1,
        ], $tenantAttrs));

        $shop = Shop::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => '测试店铺',
            'code' => 'SHOP_'.uniqid(),
            'subdomain' => 'test-shop',
            'status' => 1,
        ], $shopAttrs));

        return [$tenant, $shop];
    }

    public function test_resolves_shop_from_subdomain_host(): void
    {
        [$tenant, $shop] = $this->createTenantAndShop([], ['subdomain' => 'acme-cn']);

        $response = $this->getJson($this->urlFor('acme-cn.platform.local'));

        $response->assertOk()
            ->assertJsonPath('shop_id', $shop->id)
            ->assertJsonPath('shop_subdomain', 'acme-cn')
            ->assertJsonPath('tenant_id', $tenant->id);
    }

    public function test_resolves_shop_from_x_shop_subdomain_header(): void
    {
        $this->createTenantAndShop([], ['subdomain' => 'header-shop']);

        $response = $this->withHeaders(['X-Shop-Subdomain' => 'header-shop'])
            ->getJson($this->urlFor('something.else.com'));

        $response->assertOk()
            ->assertJsonPath('shop_subdomain', 'header-shop');
    }

    public function test_header_takes_precedence_over_host(): void
    {
        [, $shopFromHeader] = $this->createTenantAndShop([], ['subdomain' => 'from-header']);
        $this->createTenantAndShop(
            ['code' => 'T2'],
            ['subdomain' => 'from-host', 'code' => 'SHOP_T2']
        );

        $response = $this->withHeaders(['X-Shop-Subdomain' => 'from-header'])
            ->getJson($this->urlFor('from-host.platform.local'));

        $response->assertOk()
            ->assertJsonPath('shop_id', $shopFromHeader->id);
    }

    public function test_returns_400_when_host_is_root_domain(): void
    {
        $response = $this->getJson($this->urlFor('platform.local'));

        $response->assertStatus(400)
            ->assertJsonPath('code', 400);
    }

    public function test_returns_400_when_host_does_not_match_platform(): void
    {
        $this->createTenantAndShop([], ['subdomain' => 'acme-cn']);

        $response = $this->getJson($this->urlFor('acme-cn.different-domain.com'));

        $response->assertStatus(400);
    }

    public function test_returns_400_for_reserved_subdomain(): void
    {
        $response = $this->getJson($this->urlFor('www.platform.local'));

        $response->assertStatus(400);
    }

    public function test_returns_400_for_nested_subdomain(): void
    {
        $response = $this->getJson($this->urlFor('a.b.platform.local'));

        $response->assertStatus(400);
    }

    public function test_returns_404_when_shop_not_found(): void
    {
        $response = $this->getJson($this->urlFor('nonexistent.platform.local'));

        $response->assertStatus(404)
            ->assertJsonPath('code', 404);
    }

    public function test_returns_404_when_shop_status_is_disabled(): void
    {
        $this->createTenantAndShop([], ['subdomain' => 'disabled-shop', 'status' => 0]);

        $response = $this->getJson($this->urlFor('disabled-shop.platform.local'));

        $response->assertStatus(404);
    }

    public function test_returns_403_when_tenant_is_disabled(): void
    {
        $this->createTenantAndShop(
            ['status' => 0],
            ['subdomain' => 'tenant-off']
        );

        $response = $this->getJson($this->urlFor('tenant-off.platform.local'));

        $response->assertStatus(403);
    }

    public function test_returns_403_when_tenant_is_expired(): void
    {
        $this->createTenantAndShop(
            ['expired_at' => now()->subDay()],
            ['subdomain' => 'expired-shop']
        );

        $response = $this->getJson($this->urlFor('expired-shop.platform.local'));

        $response->assertStatus(403);
    }

    public function test_invalid_subdomain_format_rejected(): void
    {
        // 通过 header 传递非法子域名字符（URL 构造阶段会直接拒绝特殊字符，
        // 所以改由 X-Shop-Subdomain 触发 sanitize 正则拒绝逻辑）
        $response = $this->withHeaders(['X-Shop-Subdomain' => 'Bad_Subdomain!'])
            ->getJson($this->urlFor('platform.local'));

        $response->assertStatus(400);
    }

    public function test_resolves_shop_across_tenant_scope_anonymously(): void
    {
        [, $shop] = $this->createTenantAndShop([], ['subdomain' => 'anon-test']);

        $response = $this->getJson($this->urlFor('anon-test.platform.local'));

        $response->assertOk()
            ->assertJsonPath('shop_subdomain', 'anon-test');

        $this->assertSame($shop->id, $response->json('shop_id'));
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Customer;
use App\Models\Tenant;
use App\Services\Api\Shop\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SetsUpCustomerPassport;
use Tests\TestCase;

/**
 * 邮箱注册流程：send-code → register → token + profile + 自动登录态。
 */
class AuthEmailRegisterTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpCustomerPassport;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCustomerPassport();
        $this->tenantId = $this->ensureDefaultTenant()->id;
    }

    private function tenantHeaders(): array
    {
        return ['X-Tenant-Id' => (string) $this->tenantId];
    }

    public function test_send_code_returns_success_without_revealing_code(): void
    {
        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/send-code', [
                'type' => 'email',
                'target' => 'alice@example.com',
            ]);

        $response->assertOk()->assertJsonPath('code', 200);
        // 不能在响应里泄露验证码
        $this->assertArrayNotHasKey('code', $response->json('data') ?? []);

        // 缓存中确实写入了 6 位验证码
        $code = app(VerificationCodeService::class)->peek('email', 'alice@example.com', $this->tenantId);
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_send_code_rejects_invalid_email(): void
    {
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/send-code', [
                'type' => 'email',
                'target' => 'not-an-email',
            ])
            ->assertStatus(422);
    }

    public function test_send_code_requires_tenant_header(): void
    {
        $this->postJson('/api/v1/shop/auth/send-code', [
            'type' => 'email',
            'target' => 'alice@example.com',
        ])->assertStatus(400);
    }

    public function test_register_with_valid_code_creates_customer_and_returns_token(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'alice@example.com', $this->tenantId);

        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'alice@example.com',
                'password' => 'secret123',
                'code' => $code,
                'name' => 'Alice',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['accessToken', 'tokenType', 'expiresIn', 'profile']])
            ->assertJsonPath('data.profile.email', 'alice@example.com')
            ->assertJsonPath('data.profile.name', 'Alice')
            ->assertJsonPath('data.tokenType', 'Bearer');

        $customer = Customer::where('email', 'alice@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame($this->tenantId, (int) $customer->tenant_id);
        $this->assertSame(1, (int) $customer->status);

        // 验证码用过即销毁
        $this->assertNull($codes->peek('email', 'alice@example.com', $this->tenantId));
    }

    public function test_register_with_wrong_code_fails(): void
    {
        $codes = app(VerificationCodeService::class);
        $codes->send('email', 'bob@example.com', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'bob@example.com',
                'password' => 'secret123',
                'code' => '000000',
            ])
            ->assertStatus(400);

        $this->assertNull(Customer::where('email', 'bob@example.com')->first());
    }

    public function test_register_with_expired_code_fails(): void
    {
        // 不发送验证码，直接尝试注册
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'eve@example.com',
                'password' => 'secret123',
                'code' => '123456',
            ])
            ->assertStatus(400);
    }

    public function test_register_email_normalized_to_lowercase(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'Carol@Example.COM', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'Carol@Example.COM',
                'password' => 'secret123',
                'code' => $code,
            ])->assertOk();

        $this->assertNotNull(Customer::where('email', 'carol@example.com')->first());
    }

    public function test_duplicate_email_rejected(): void
    {
        Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'dup@example.com',
            'password' => 'pwd',
            'status' => 1,
        ]);

        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'dup@example.com', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'dup@example.com',
                'password' => 'newpass',
                'code' => $code,
            ])->assertStatus(400);
    }

    public function test_same_email_can_register_in_different_tenants(): void
    {
        $other = $this->createOtherTenant();
        Customer::create([
            'tenant_id' => $this->tenantId,
            'email' => 'cross@example.com',
            'password' => 'pwd',
            'status' => 1,
        ]);

        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'cross@example.com', $other->id);

        $this->withHeaders(['X-Tenant-Id' => (string) $other->id])
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'cross@example.com',
                'password' => 'newpass',
                'code' => $code,
            ])->assertOk();

        $this->assertSame(2, Customer::where('email', 'cross@example.com')->count());
    }

    public function test_password_validation_min_length(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'short@example.com', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'short@example.com',
                'password' => '123', // < 6
                'code' => $code,
            ])->assertStatus(422);
    }

    public function test_me_endpoint_requires_token(): void
    {
        $this->getJson('/api/v1/shop/auth/me')->assertStatus(401);
    }

    public function test_me_endpoint_returns_customer_profile_with_token(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'tok@example.com', $this->tenantId);

        $register = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'tok@example.com',
                'password' => 'secret123',
                'code' => $code,
            ])->json('data');

        $token = $register['accessToken'];

        $me = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/shop/auth/me');

        $me->assertOk()
            ->assertJsonPath('data.email', 'tok@example.com');
    }

    public function test_logout_revokes_token(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('email', 'out@example.com', $this->tenantId);

        $token = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => 'out@example.com',
                'password' => 'secret123',
                'code' => $code,
            ])->json('data.accessToken');

        // 用 token 调 me 应当成功
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/shop/auth/me')->assertOk();

        // 登出
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shop/auth/logout')->assertOk();

        // 测试环境下 Laravel 容器跨请求复用，TokenGuard 缓存了 $user，需要手动清除
        Auth::forgetGuards();

        // 同 token 再访问应失败
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/shop/auth/me')->assertStatus(401);
    }

    private function createOtherTenant(): Tenant
    {
        return Tenant::create([
            'code' => 'OTHER',
            'name' => 'Other tenant',
            'status' => 1,
            'primary_domain' => 'other.example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}

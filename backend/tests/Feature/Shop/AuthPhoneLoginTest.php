<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Customer;
use App\Models\Tenant;
use App\Services\Api\Shop\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetsUpCustomerPassport;
use Tests\TestCase;

/**
 * 手机号 + 邮箱登录流程：密码登录 + 验证码登录（首次自动建号）。
 */
class AuthPhoneLoginTest extends TestCase
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

    private function tenantHeaders(int $tenantId = 0): array
    {
        return ['X-Tenant-Id' => (string) ($tenantId > 0 ? $tenantId : $this->tenantId)];
    }

    private function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $this->tenantId,
            'phone' => '13900000001',
            'password' => 'pass1234',
            'name' => 'PhoneUser',
            'status' => 1,
        ], $overrides));
    }

    public function test_login_with_phone_password_returns_token(): void
    {
        $this->makeCustomer(['phone' => '13900000001', 'password' => 'pass1234']);

        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13900000001',
                'password' => 'pass1234',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['accessToken', 'tokenType', 'expiresIn', 'profile']])
            ->assertJsonPath('data.profile.phone', '13900000001');
    }

    public function test_login_with_email_password_returns_token(): void
    {
        $this->makeCustomer(['email' => 'mail@example.com', 'phone' => '', 'password' => 'pass1234']);

        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => 'mail@example.com',
                'password' => 'pass1234',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.profile.email', 'mail@example.com');
    }

    public function test_login_wrong_password_fails(): void
    {
        $this->makeCustomer(['phone' => '13900000001', 'password' => 'pass1234']);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13900000001',
                'password' => 'wrong-pwd',
            ])->assertStatus(400);
    }

    public function test_login_nonexistent_account_fails(): void
    {
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13988888888',
                'password' => 'whatever',
            ])->assertStatus(400);
    }

    public function test_login_disabled_account_returns_400(): void
    {
        $this->makeCustomer(['phone' => '13900000001', 'password' => 'pass1234', 'status' => 0]);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13900000001',
                'password' => 'pass1234',
            ])->assertStatus(400);
    }

    public function test_login_does_not_cross_tenant_boundary(): void
    {
        // 在租户 A 创建账号
        $this->makeCustomer(['phone' => '13900000001', 'password' => 'pass1234']);

        // 切到租户 B 用同账号尝试登录 → 失败
        $other = Tenant::create([
            'code' => 'OTHER',
            'name' => 'Other tenant',
            'status' => 1,
            'primary_domain' => 'other.example.com',
        ]);

        $this->withHeaders(['X-Tenant-Id' => (string) $other->id])
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13900000001',
                'password' => 'pass1234',
            ])->assertStatus(400);
    }

    public function test_login_by_code_creates_account_for_first_time_phone(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('phone', '13911112222', $this->tenantId);

        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login-by-code', [
                'type' => 'phone',
                'target' => '13911112222',
                'code' => $code,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['accessToken', 'profile']])
            ->assertJsonPath('data.profile.phone', '13911112222');

        $this->assertNotNull(
            Customer::where('phone', '13911112222')->where('tenant_id', $this->tenantId)->first()
        );
    }

    public function test_login_by_code_uses_existing_account(): void
    {
        $existing = $this->makeCustomer(['phone' => '13900000001']);

        $codes = app(VerificationCodeService::class);
        $code = $codes->send('phone', '13900000001', $this->tenantId);

        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login-by-code', [
                'type' => 'phone',
                'target' => '13900000001',
                'code' => $code,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.profile.id', $existing->id);

        // 不应额外创建用户
        $this->assertSame(1, Customer::where('phone', '13900000001')->count());
    }

    public function test_login_by_code_wrong_code_fails(): void
    {
        $codes = app(VerificationCodeService::class);
        $codes->send('phone', '13900000001', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login-by-code', [
                'type' => 'phone',
                'target' => '13900000001',
                'code' => '000000',
            ])->assertStatus(400);
    }

    public function test_login_by_code_disabled_account_fails(): void
    {
        $this->makeCustomer(['phone' => '13900000001', 'status' => 0]);

        $codes = app(VerificationCodeService::class);
        $code = $codes->send('phone', '13900000001', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login-by-code', [
                'type' => 'phone',
                'target' => '13900000001',
                'code' => $code,
            ])->assertStatus(400);
    }

    public function test_invalid_phone_format_rejected_by_validator(): void
    {
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/send-code', [
                'type' => 'phone',
                'target' => 'abc-def',
            ])->assertStatus(422);
    }

    public function test_phone_with_country_prefix_accepted(): void
    {
        $codes = app(VerificationCodeService::class);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/send-code', [
                'type' => 'phone',
                'target' => '+8613900001111',
            ])->assertOk();

        $this->assertNotNull($codes->peek('phone', '+8613900001111', $this->tenantId));
    }

    public function test_password_is_hashed_on_register_via_attribute_cast(): void
    {
        $codes = app(VerificationCodeService::class);
        $code = $codes->send('phone', '13900003333', $this->tenantId);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'phone',
                'target' => '13900003333',
                'password' => 'plaintext-pwd',
                'code' => $code,
            ])->assertOk();

        $stored = Customer::where('phone', '13900003333')->first()->getAttributes()['password'];
        $this->assertNotSame('plaintext-pwd', $stored);
        $this->assertTrue(Hash::check('plaintext-pwd', $stored));
    }

    public function test_login_updates_last_login_at(): void
    {
        $customer = $this->makeCustomer(['phone' => '13900000001', 'password' => 'pass1234']);
        $this->assertNull($customer->last_login_at);

        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13900000001',
                'password' => 'pass1234',
            ])->assertOk();

        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\Mall\Customer;
use App\Services\Api\Shop\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\SetsUpCustomerPassport;
use Tests\TestCase;

/**
 * Shop Auth 路由的节流（throttle:5,1 = 每分钟 5 次）。
 *
 * 节流粒度由 Laravel 默认 RateLimiter 决定（IP + route），同一 IP 高频请求触发 429。
 */
class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use SetsUpCustomerPassport;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCustomerPassport();
        $this->tenantId = $this->ensureDefaultTenant()->id;
        RateLimiter::clear($this->throttleKey('shop/auth/send-code'));
        RateLimiter::clear($this->throttleKey('shop/auth/login'));
    }

    private function tenantHeaders(): array
    {
        return ['X-Tenant-Id' => (string) $this->tenantId];
    }

    private function throttleKey(string $signature): string
    {
        return sha1($signature);
    }

    public function test_send_code_throttled_after_5_attempts_per_minute(): void
    {
        // 前 5 次成功
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->tenantHeaders())
                ->postJson('/api/v1/shop/auth/send-code', [
                    'type' => 'email',
                    'target' => "user{$i}@example.com",
                ])->assertOk();
        }

        // 第 6 次应被节流
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/send-code', [
                'type' => 'email',
                'target' => 'user6@example.com',
            ])->assertStatus(429);
    }

    public function test_login_throttled_after_5_attempts_per_minute(): void
    {
        Customer::create([
            'tenant_id' => $this->tenantId,
            'phone' => '13900000001',
            'password' => 'pass1234',
            'status' => 1,
        ]);

        // 前 5 次（不论对错）
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->tenantHeaders())
                ->postJson('/api/v1/shop/auth/login', [
                    'username' => '13900000001',
                    'password' => 'wrong',
                ])->assertStatus(400);
        }

        // 第 6 次 → 429
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login', [
                'username' => '13900000001',
                'password' => 'pass1234',
            ])->assertStatus(429);
    }

    public function test_register_endpoint_is_throttled(): void
    {
        $codes = app(VerificationCodeService::class);

        for ($i = 0; $i < 5; $i++) {
            $email = "regsuc{$i}@example.com";
            $code = $codes->send('email', $email, $this->tenantId);
            $this->withHeaders($this->tenantHeaders())
                ->postJson('/api/v1/shop/auth/register', [
                    'type' => 'email',
                    'target' => $email,
                    'password' => 'pass1234',
                    'code' => $code,
                ])->assertOk();
        }

        // 第 6 次 → 429
        $email = 'reg6@example.com';
        $code = $codes->send('email', $email, $this->tenantId);
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/register', [
                'type' => 'email',
                'target' => $email,
                'password' => 'pass1234',
                'code' => $code,
            ])->assertStatus(429);
    }

    public function test_login_by_code_endpoint_is_throttled(): void
    {
        $codes = app(VerificationCodeService::class);

        // login-by-code 与 login 共享一个端点路径前缀，但路径不同 → 计数不共享
        for ($i = 0; $i < 5; $i++) {
            $phone = "139000000{$i}{$i}";
            $code = $codes->send('phone', $phone, $this->tenantId);
            $this->withHeaders($this->tenantHeaders())
                ->postJson('/api/v1/shop/auth/login-by-code', [
                    'type' => 'phone',
                    'target' => $phone,
                    'code' => $code,
                ])->assertOk();
        }

        $phone = '13988887777';
        $code = $codes->send('phone', $phone, $this->tenantId);
        $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/login-by-code', [
                'type' => 'phone',
                'target' => $phone,
                'code' => $code,
            ])->assertStatus(429);
    }

    public function test_throttle_429_returns_json_error_payload(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($this->tenantHeaders())
                ->postJson('/api/v1/shop/auth/send-code', [
                    'type' => 'email',
                    'target' => "u{$i}@example.com",
                ]);
        }

        $response = $this->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/shop/auth/send-code', [
                'type' => 'email',
                'target' => 'overflow@example.com',
            ]);

        // 节流时返回 429 + JSON 响应（content-type 为 application/json，
        // 由 API 异常处理器输出统一 { code, msg } 结构）
        $response->assertStatus(429);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}

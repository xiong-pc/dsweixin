<?php

namespace App\Services\Api\Shop;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 验证码生成 + 校验（P0 stub，日志发送）。
 *
 * P1 接入真实邮件 / 短信驱动后只需替换 deliver() 方法。
 *
 * 缓存键格式：vcode:{type}:{tenant_id}:{normalized_target}
 *   - type: email | phone
 *   - tenant_id: 租户隔离（同手机号在不同租户互不干扰）
 *   - target: 邮箱地址 / 手机号（小写归一）
 */
class VerificationCodeService
{
    private const CODE_TTL_MINUTES = 10;

    private const CODE_LENGTH = 6;

    /**
     * 生成验证码并下发（P0：日志输出）。
     *
     * @return string 验证码（仅供测试断言；生产环境永不返回给客户端）
     */
    public function send(string $type, string $target, int $tenantId): string
    {
        $this->assertType($type);
        if ($target === '') {
            throw new BusinessException('api.code_target_required');
        }

        $code = $this->generateCode();
        $key = $this->cacheKey($type, $target, $tenantId);

        Cache::put($key, $code, now()->addMinutes(self::CODE_TTL_MINUTES));
        $this->deliver($type, $target, $code);

        return $code;
    }

    /**
     * 校验验证码；通过后立即销毁（一次性）。
     */
    public function verify(string $type, string $target, int $tenantId, string $code): bool
    {
        $this->assertType($type);
        $key = $this->cacheKey($type, $target, $tenantId);
        $stored = Cache::get($key);

        if (! is_string($stored) || $stored === '' || ! hash_equals($stored, $code)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    /**
     * 仅供测试 / 调试：取出当前缓存中的验证码。
     */
    public function peek(string $type, string $target, int $tenantId): ?string
    {
        $value = Cache::get($this->cacheKey($type, $target, $tenantId));

        return is_string($value) ? $value : null;
    }

    private function generateCode(): string
    {
        $max = (int) (10 ** self::CODE_LENGTH) - 1;

        return str_pad((string) random_int(0, $max), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, ['email', 'phone'], true)) {
            throw new BusinessException('api.code_type_invalid');
        }
    }

    private function cacheKey(string $type, string $target, int $tenantId): string
    {
        $normalized = strtolower(trim($target));

        return sprintf('vcode:%s:%d:%s', $type, $tenantId, $normalized);
    }

    /**
     * P0 stub：日志发送。生产环境替换为邮件 / 短信驱动。
     */
    protected function deliver(string $type, string $target, string $code): void
    {
        Log::channel(config('logging.default', 'stack'))->info('[shop.auth] verification code delivered', [
            'type' => $type,
            'target' => $target,
            'code' => $code,
            'driver' => 'stub-log',
        ]);
    }
}

<?php

namespace App\Services\Api\Shop;

use App\Exceptions\BusinessException;
use App\Models\Mall\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Token;

/**
 * 商城前台客户身份服务（注册 / 登录 / token 颁发）。
 *
 * Token 机制：复用 Passport 个人访问令牌（与 admin User 使用同一 personal access client，
 * 但通过 `passport-customer` guard 的 `customers` provider 隔离用户解析）。
 *
 * 路由侧：customer 端点 `/api/v1/shop/*` 走 `auth:passport-customer` 中间件，
 * admin 端点继续走 `auth:api`，互不交叉。
 */
class AuthService
{
    public function __construct(private readonly VerificationCodeService $codes) {}

    /**
     * 邮箱 / 手机注册（必须验证码）。
     *
     * @param  array{type: string, target: string, password: string, code: string, name?: string, tenant_id: int, shop_id?: int|null}  $data
     */
    public function register(array $data): Customer
    {
        $type = $data['type'];
        $target = trim($data['target']);
        $tenantId = (int) $data['tenant_id'];

        if (! $this->codes->verify($type, $target, $tenantId, $data['code'])) {
            throw new BusinessException('api.verification_code_invalid');
        }

        if ($this->findByTarget($type, $target, $tenantId) !== null) {
            throw new BusinessException('api.account_already_exists');
        }

        return DB::transaction(function () use ($type, $target, $data, $tenantId) {
            return Customer::create([
                'tenant_id' => $tenantId,
                'shop_id' => $data['shop_id'] ?? null,
                'email' => $type === 'email' ? strtolower($target) : '',
                'phone' => $type === 'phone' ? $target : '',
                'password' => $data['password'],
                'name' => $data['name'] ?? '',
                'status' => 1,
            ]);
        });
    }

    /**
     * 密码登录（邮箱或手机）。
     */
    public function loginByPassword(string $username, string $password, int $tenantId): Customer
    {
        $type = $this->detectType($username);
        $customer = $this->findByTarget($type, trim($username), $tenantId);

        if ($customer === null) {
            throw new BusinessException('api.invalid_credentials');
        }
        if ((int) $customer->status !== 1) {
            throw new BusinessException('api.account_disabled');
        }
        if (! Hash::check($password, $customer->password)) {
            throw new BusinessException('api.invalid_credentials');
        }

        return $customer;
    }

    /**
     * 验证码登录：账号不存在则自动注册（首次登录创号常见前台模式）。
     */
    public function loginByCode(string $type, string $target, string $code, int $tenantId): Customer
    {
        $target = trim($target);

        if (! $this->codes->verify($type, $target, $tenantId, $code)) {
            throw new BusinessException('api.verification_code_invalid');
        }

        $customer = $this->findByTarget($type, $target, $tenantId);
        if ($customer === null) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'email' => $type === 'email' ? strtolower($target) : '',
                'phone' => $type === 'phone' ? $target : '',
                'password' => Hash::make(str()->random(32)),
                'status' => 1,
            ]);
        } elseif ((int) $customer->status !== 1) {
            throw new BusinessException('api.account_disabled');
        }

        return $customer;
    }

    /**
     * 颁发 Passport 个人访问令牌。
     *
     * @return array{accessToken: string, tokenType: string, expiresIn: int}
     */
    public function issueToken(Customer $customer): array
    {
        $token = $customer->createToken('Customer Access Token');

        $customer->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => (string) (request()->ip() ?? ''),
        ])->save();

        return [
            'accessToken' => $token->accessToken,
            'tokenType' => 'Bearer',
            'expiresIn' => (int) config('passport.token_expire_days', 15) * 86400,
        ];
    }

    /**
     * 登出：撤销当前 token；fallback 撤销该 customer 所有 token（安全优先）。
     */
    public function logout(Customer $customer): void
    {
        $token = $customer->token();
        if ($token instanceof Token) {
            $token->revoke();

            return;
        }

        // 兜底：撤销所有未撤销 token（例如 token() 拿不到当前实例时）
        $customer->tokens()->where('revoked', false)->update(['revoked' => true]);
    }

    public function detectType(string $value): string
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'phone';
    }

    private function findByTarget(string $type, string $target, int $tenantId): ?Customer
    {
        $column = $type === 'email' ? 'email' : 'phone';
        $value = $type === 'email' ? strtolower($target) : $target;

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->where($column, $value)
            ->first();
    }
}

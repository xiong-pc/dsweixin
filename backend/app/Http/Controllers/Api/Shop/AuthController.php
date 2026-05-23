<?php

namespace App\Http\Controllers\Api\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Shop\Auth\LoginByCodeRequest;
use App\Http\Requests\Api\Shop\Auth\LoginRequest;
use App\Http\Requests\Api\Shop\Auth\RegisterRequest;
use App\Http\Requests\Api\Shop\Auth\SendCodeRequest;
use App\Models\Mall\Customer;
use App\Services\Api\Shop\AuthService;
use App\Services\Api\Shop\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 商城前台客户身份端点。
 *
 * 公开端点（throttle 节流）：send-code / register / login / login-by-code
 * 受保护端点（auth:passport-customer）：me / logout
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly VerificationCodeService $codes,
    ) {}

    /**
     * 发送邮箱 / 短信验证码。
     */
    public function sendCode(SendCodeRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $this->codes->send(
            (string) $request->input('type'),
            (string) $request->input('target'),
            $tenantId
        );

        // 不回传验证码到客户端
        return $this->success(null, 'api.verification_code_sent');
    }

    /**
     * 邮箱 / 手机注册（必须验证码）。
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $shopId = $this->resolveShopId($request);

        $customer = $this->auth->register([
            'type' => (string) $request->input('type'),
            'target' => (string) $request->input('target'),
            'password' => (string) $request->input('password'),
            'code' => (string) $request->input('code'),
            'name' => $request->input('name'),
            'tenant_id' => $tenantId,
            'shop_id' => $shopId,
        ]);

        return $this->success(
            array_merge(
                $this->auth->issueToken($customer),
                ['profile' => $this->profile($customer->fresh() ?? $customer)]
            ),
            'api.register_success'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $customer = $this->auth->loginByPassword(
            (string) $request->input('username'),
            (string) $request->input('password'),
            $tenantId
        );

        return $this->success(
            array_merge(
                $this->auth->issueToken($customer),
                ['profile' => $this->profile($customer->fresh() ?? $customer)]
            ),
            'api.login_success'
        );
    }

    public function loginByCode(LoginByCodeRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $customer = $this->auth->loginByCode(
            (string) $request->input('type'),
            (string) $request->input('target'),
            (string) $request->input('code'),
            $tenantId
        );

        return $this->success(
            array_merge(
                $this->auth->issueToken($customer),
                ['profile' => $this->profile($customer->fresh() ?? $customer)]
            ),
            'api.login_success'
        );
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof Customer) {
            return $this->error('api.unauthorized', 401);
        }

        return $this->success($this->profile($customer));
    }

    public function logout(Request $request): JsonResponse
    {
        $customer = $request->user();
        if ($customer instanceof Customer) {
            $this->auth->logout($customer);
        }

        return $this->success(null, 'api.logout_success');
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'shop_id' => $customer->shop_id,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'name' => $customer->name,
            'avatar' => $customer->avatar,
            'gender' => $customer->gender,
            'locale' => $customer->locale,
            'currency' => $customer->currency,
            'status' => $customer->status,
        ];
    }

    private function resolveTenantId(Request $request): int
    {
        $tenantId = (int) ($request->header('X-Tenant-Id') ?? '');
        if ($tenantId <= 0) {
            throw new BusinessException('api.tenant_required', 400);
        }

        return $tenantId;
    }

    private function resolveShopId(Request $request): ?int
    {
        $shopId = (int) ($request->header('X-Shop-Id') ?? '');

        return $shopId > 0 ? $shopId : null;
    }
}

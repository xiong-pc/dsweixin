<?php

namespace App\Http\Controllers\Api\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Shop\Checkout\PlaceOrderRequest;
use App\Http\Resources\Api\Shop\OrderResource;
use App\Services\Api\Shop\CartService;
use App\Services\Api\Shop\CheckoutService;
use App\Services\Api\Shop\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checkout = preview + place 闭环。
 *  - preview：不写库，只返回当前购物车的最终价格 + 库存可用性
 *  - place：等价于 OrderService.createFromCart（预占 + 快照 + 清空购物车）
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly OrderService $orderService,
        private readonly CartService $cartService,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $shopId = $this->resolveShopId($request);
        [$customerId, $sessionId] = $this->resolveIdentity($request);

        if ($customerId === null && $sessionId === null) {
            throw new BusinessException('api.cart_identity_required');
        }

        $locale = $request->header('X-Locale');
        $currency = $request->header('X-Currency');

        $cart = $this->cartService->resolveOrCreate(
            $tenantId, $shopId, $customerId, $sessionId,
            is_string($locale) && $locale !== '' ? $locale : null,
            is_string($currency) && $currency !== '' ? $currency : null,
        );

        return $this->success($this->checkout->preview($cart));
    }

    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $shopId = $this->resolveShopId($request);
        [$customerId, $sessionId] = $this->resolveIdentity($request);

        if ($customerId === null && $sessionId === null) {
            throw new BusinessException('api.cart_identity_required');
        }

        $cart = $this->cartService->resolveOrCreate($tenantId, $shopId, $customerId, $sessionId);

        $data = $request->validated();
        $extra = [];
        if (isset($data['billing_address'])) {
            $extra['billing_address'] = $data['billing_address'];
        }
        if (isset($data['remark'])) {
            $extra['remark'] = $data['remark'];
        }

        $order = $this->orderService->createFromCart($cart, $data['shipping_address'], $extra);

        return $this->success(new OrderResource($order), 'api.created');
    }

    private function resolveTenantId(Request $request): int
    {
        $tenantId = (int) ($request->header('X-Tenant-Id') ?? '');
        if ($tenantId <= 0) {
            throw new BusinessException('api.cart_identity_required');
        }

        return $tenantId;
    }

    private function resolveShopId(Request $request): ?int
    {
        $shopId = (int) ($request->header('X-Shop-Id') ?? '');

        return $shopId > 0 ? $shopId : null;
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveIdentity(Request $request): array
    {
        $customerId = (int) ($request->header('X-Customer-Id') ?? '');
        $sessionId = (string) ($request->header('X-Session-Id') ?? '');

        return [
            $customerId > 0 ? $customerId : null,
            $sessionId !== '' ? $sessionId : null,
        ];
    }
}

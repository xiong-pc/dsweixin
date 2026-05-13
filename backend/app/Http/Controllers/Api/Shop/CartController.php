<?php

namespace App\Http\Controllers\Api\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Shop\Cart\AddCartItemRequest;
use App\Http\Requests\Api\Shop\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\Shop\CartResource;
use App\Models\Mall\Cart;
use App\Models\Mall\CartItem;
use App\Services\Api\Shop\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $service) {}

    /**
     * GET /api/v1/shop/cart  当前用户/会话的购物车。
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        return $this->success(new CartResource($this->service->getCartWithItems($cart)));
    }

    /**
     * POST /api/v1/shop/cart/items  添加 SKU 到购物车。
     */
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        $this->service->addItem(
            $cart,
            (int) $request->validated('variant_id'),
            (int) $request->validated('quantity'),
        );

        return $this->success(
            new CartResource($this->service->getCartWithItems($cart->fresh())),
            'api.created'
        );
    }

    /**
     * PUT /api/v1/shop/cart/items/{item}  改数量。
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        $this->ensureItemAccess($request, $item);

        $this->service->updateItemQuantity($item, (int) $request->validated('quantity'));

        return $this->success(null, 'api.updated');
    }

    /**
     * DELETE /api/v1/shop/cart/items/{item}  删除某 item。
     */
    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $this->ensureItemAccess($request, $item);

        $this->service->removeItem($item);

        return $this->success(null, 'api.deleted');
    }

    /**
     * DELETE /api/v1/shop/cart  清空购物车。
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $this->service->clear($cart);

        return $this->success(null, 'api.deleted');
    }

    /**
     * POST /api/v1/shop/cart/merge  游客登录后调用，把 session 购物车合并到 customer。
     * Body: customer_id（或从 header 取）；session_id 从 header 取。
     */
    public function merge(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $shopId = $this->resolveShopId($request);

        $customerId = (int) ($request->input('customer_id') ?? $request->header('X-Customer-Id') ?? '');
        $sessionId = (string) ($request->header('X-Session-Id') ?? '');

        if ($customerId <= 0 || $sessionId === '') {
            throw new BusinessException('api.cart_identity_required');
        }

        $cart = $this->service->mergeGuestIntoCustomer($tenantId, $shopId, $sessionId, $customerId);

        return $this->success(new CartResource($this->service->getCartWithItems($cart)), 'api.updated');
    }

    /**
     * 从 request 解析当前购物车 — 4 种身份场景。
     */
    private function resolveCart(Request $request): Cart
    {
        $tenantId = $this->resolveTenantId($request);
        $shopId = $this->resolveShopId($request);
        [$customerId, $sessionId] = $this->resolveIdentity($request);
        $locale = $request->header('X-Locale');
        $currency = $request->header('X-Currency');

        return $this->service->resolveOrCreate(
            $tenantId, $shopId, $customerId,
            $sessionId,
            is_string($locale) && $locale !== '' ? $locale : null,
            is_string($currency) && $currency !== '' ? $currency : null,
        );
    }

    private function resolveTenantId(Request $request): int
    {
        $user = $request->user();
        if ($user !== null && (int) ($user->tenant_id ?? 0) > 0) {
            return (int) $user->tenant_id;
        }

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
     * @return array{0: int|null, 1: string|null} customerId, sessionId
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

    private function ensureItemAccess(Request $request, CartItem $item): void
    {
        $item->loadMissing('cart');
        /** @var Cart|null $cart */
        $cart = $item->cart;
        if ($cart === null) {
            abort(404);
        }

        $tenantId = $this->resolveTenantId($request);
        if ((int) $cart->tenant_id !== $tenantId) {
            abort(403);
        }

        [$customerId, $sessionId] = $this->resolveIdentity($request);

        if ($customerId !== null) {
            if ((int) ($cart->customer_id ?? 0) !== $customerId) {
                abort(403);
            }
        } elseif ($sessionId !== null) {
            if ((string) $cart->session_id !== $sessionId) {
                abort(403);
            }
        } else {
            abort(401);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Shop\Order\CreateOrderRequest;
use App\Http\Resources\Api\Shop\OrderResource;
use App\Models\Mall\Order;
use App\Services\Api\Shop\CartService;
use App\Services\Api\Shop\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $service,
        private readonly CartService $cartService,
    ) {}

    /**
     * 列出当前身份下的订单。
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        [$customerId, $sessionId] = $this->resolveIdentity($request);

        if ($customerId === null && $sessionId === null) {
            throw new BusinessException('api.cart_identity_required');
        }

        $query = Order::query()->where('tenant_id', $tenantId)->with(['items', 'shippingAddress', 'shipments']);
        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        } else {
            $query->whereNull('customer_id')->where('session_id', $sessionId);
        }

        return $this->paginate(
            $query->orderByDesc('id')->paginate(
                (int) $request->input('pageSize', 20),
                ['*'],
                'page',
                (int) $request->input('pageNum', 1)
            ),
            OrderResource::class
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->ensureOrderAccess($request, $order);

        return $this->success(new OrderResource(
            $order->load(['items', 'shippingAddress', 'billingAddress', 'shipments'])
        ));
    }

    /**
     * 从购物车下单。
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $shopId = $this->resolveShopId($request);
        [$customerId, $sessionId] = $this->resolveIdentity($request);

        if ($customerId === null && $sessionId === null) {
            throw new BusinessException('api.cart_identity_required');
        }

        $cart = $this->cartService->resolveOrCreate(
            $tenantId, $shopId, $customerId, $sessionId
        );

        $data = $request->validated();
        $extra = [];
        if (isset($data['billing_address'])) {
            $extra['billing_address'] = $data['billing_address'];
        }
        if (isset($data['remark'])) {
            $extra['remark'] = $data['remark'];
        }

        $order = $this->service->createFromCart($cart, $data['shipping_address'], $extra);

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

    private function ensureOrderAccess(Request $request, Order $order): void
    {
        $tenantId = $this->resolveTenantId($request);
        if ((int) $order->tenant_id !== $tenantId) {
            abort(403);
        }

        [$customerId, $sessionId] = $this->resolveIdentity($request);

        if ($customerId !== null) {
            if ((int) ($order->customer_id ?? 0) !== $customerId) {
                abort(403);
            }
        } elseif ($sessionId !== null) {
            if ((string) $order->session_id !== $sessionId) {
                abort(403);
            }
        } else {
            abort(401);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Order\CancelOrderRequest;
use App\Http\Requests\Api\Mall\Order\RefundOrderRequest;
use App\Http\Requests\Api\Mall\Order\ShipOrderRequest;
use App\Http\Resources\Api\Shop\OrderResource;
use App\Models\Mall\Order;
use App\Services\Api\Mall\OrderShipmentService;
use App\Services\Api\Payment\RefundService;
use App\Services\Api\Shop\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 后台订单管理（M08-PR33）：列表/详情 + 发货/退款/取消三个动作。
 *
 * 路由：/api/v1/mall/orders
 *
 * 与 Shop\OrderController 区别：
 *   - 这里是 admin 后台接口（需 auth + tenant 隔离）
 *   - Shop\OrderController 是 customer 前台接口（session/customer 身份）
 *
 * 复用现有 service：
 *   - 发货 → OrderShipmentService::ship
 *   - 退款 → RefundService::refund
 *   - 取消 → OrderService::cancelOrder
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderShipmentService $shipmentService,
        private readonly RefundService $refundService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $query = Order::query()->with(['items', 'shippingAddress', 'shipments']);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } elseif ($request->filled('tenant_id')) {
            $query->where('tenant_id', (int) $request->input('tenant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('order_no')) {
            $query->where('order_no', 'like', '%'.$request->input('order_no').'%');
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
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
            $order->load(['items', 'shippingAddress', 'billingAddress', 'shipments', 'histories'])
        ));
    }

    /**
     * 发货：order(Paid) → 写 shipment + 推订单到 Shipped。
     */
    public function ship(ShipOrderRequest $request, Order $order): JsonResponse
    {
        $this->ensureOrderAccess($request, $order);

        $this->shipmentService->ship($order, $request->validated());

        return $this->success(
            new OrderResource($order->fresh(['items', 'shipments']) ?? $order),
            'api.created'
        );
    }

    /**
     * 退款：调 RefundService 真实退款。
     */
    public function refund(RefundOrderRequest $request, Order $order): JsonResponse
    {
        $this->ensureOrderAccess($request, $order);

        $data = $request->validated();
        $amount = isset($data['amount']) ? (float) $data['amount'] : null;
        $reason = (string) ($data['reason'] ?? '');

        $payment = $this->refundService->refund($order, $amount, $reason);

        return $this->success([
            'refund_payment_id' => (int) $payment->id,
            'order' => new OrderResource($order->fresh(['items', 'shipments']) ?? $order),
        ], 'api.updated');
    }

    /**
     * 取消订单：仅 Pending/Paid 可取消（OrderStatus 状态机限制）。
     */
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $this->ensureOrderAccess($request, $order);

        $reason = (string) ($request->validated()['reason'] ?? '');

        $user = $request->user();
        $this->orderService->cancelOrder($order, [
            'reason' => $reason,
            'operator_type' => 'user',
            'operator_id' => $user !== null ? (int) $user->id : 0,
        ]);

        return $this->success(
            new OrderResource($order->fresh(['items']) ?? $order),
            'api.updated'
        );
    }

    private function resolveTenantId(Request $request): ?int
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        if ($user->isSuperAdmin() === true) {
            return null; // 不限定，由 controller 参数或查询自行处理
        }

        return (int) $user->tenant_id;
    }

    private function ensureOrderAccess(Request $request, Order $order): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $order->tenant_id) {
            abort(403);
        }
    }
}

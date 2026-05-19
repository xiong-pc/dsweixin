<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\OrderShipment\StoreOrderShipmentRequest;
use App\Http\Requests\Api\Mall\OrderShipment\UpdateOrderShipmentRequest;
use App\Http\Resources\Api\Mall\OrderShipmentResource;
use App\Models\Mall\Order;
use App\Models\Mall\OrderShipment;
use App\Services\Api\Mall\OrderShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 订单发货 / 物流跟踪 admin 端点。
 *
 * - POST   /api/v1/mall/order-shipments               发货（创建 shipment + 推进订单到 Shipped）
 * - GET    /api/v1/mall/order-shipments               列表（可按 order_id / status / tracking_no 过滤）
 * - GET    /api/v1/mall/order-shipments/{shipment}    单条详情
 * - PUT    /api/v1/mall/order-shipments/{shipment}    修改运单号 / 承运商 / 实际运费
 * - POST   /api/v1/mall/order-shipments/{shipment}/deliver   标记签收
 * - POST   /api/v1/mall/order-shipments/{shipment}/cancel    撤销发货
 */
class OrderShipmentController extends Controller
{
    public function __construct(private readonly OrderShipmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $query = OrderShipment::query()
            ->whereHas('order', function ($q) use ($tenantId) {
                if ($tenantId !== null) {
                    $q->where('tenant_id', $tenantId);
                }
            });

        if ($request->filled('order_id')) {
            $query->where('order_id', (int) $request->input('order_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('tracking_no')) {
            $kw = (string) $request->input('tracking_no');
            $query->where('tracking_no', 'like', "%{$kw}%");
        }

        return $this->paginate(
            $query->orderByDesc('id')->paginate(
                (int) $request->input('pageSize', 20),
                ['*'],
                'page',
                (int) $request->input('pageNum', 1)
            ),
            OrderShipmentResource::class
        );
    }

    public function store(StoreOrderShipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var Order $order */
        $order = Order::query()->findOrFail((int) $data['order_id']);
        $this->ensureOrderAccess($request, $order);

        $shipment = $this->service->ship($order, $data);

        return $this->success(new OrderShipmentResource($shipment), 'api.created');
    }

    public function show(Request $request, OrderShipment $orderShipment): JsonResponse
    {
        $this->ensureShipmentAccess($request, $orderShipment);

        return $this->success(new OrderShipmentResource($orderShipment));
    }

    public function update(UpdateOrderShipmentRequest $request, OrderShipment $orderShipment): JsonResponse
    {
        $this->ensureShipmentAccess($request, $orderShipment);

        $this->service->updateTracking($orderShipment, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function deliver(Request $request, OrderShipment $orderShipment): JsonResponse
    {
        $this->ensureShipmentAccess($request, $orderShipment);

        $this->service->markDelivered($orderShipment);

        return $this->success(new OrderShipmentResource($orderShipment->fresh() ?? $orderShipment));
    }

    public function cancel(Request $request, OrderShipment $orderShipment): JsonResponse
    {
        $this->ensureShipmentAccess($request, $orderShipment);

        $this->service->cancel($orderShipment);

        return $this->success(new OrderShipmentResource($orderShipment->fresh() ?? $orderShipment));
    }

    private function resolveTenantId(Request $request): ?int
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        if ($user->isSuperAdmin() === true) {
            $forced = $request->input('tenant_id');

            return $forced !== null ? (int) $forced : null;
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

    private function ensureShipmentAccess(Request $request, OrderShipment $shipment): void
    {
        $order = $shipment->order()->first();
        if (! $order instanceof Order) {
            abort(404);
        }
        $this->ensureOrderAccess($request, $order);
    }
}

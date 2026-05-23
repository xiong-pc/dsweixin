<?php

namespace App\Services\Api\Mall;

use App\Enums\OrderShipmentStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderShipment;
use App\Services\Api\Shop\OrderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 订单发货 / 物流跟踪服务（M07-PR30）。
 *
 * 一个订单可有多条 shipment（拆包发货）。
 *
 * 状态流转：
 *   - ship() ：order(Paid) → 写一条 OrderShipment(shipped) + Order.status → Shipped（首次）
 *   - markDelivered() ：shipment(shipped) → delivered；所有 shipment 都 delivered 时 Order → Delivered
 *   - cancel() ：shipment(shipped) → cancelled（误发撤回，不动 Order 状态）
 */
class OrderShipmentService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * 发货：为订单创建 shipment 记录并推进订单状态。
     *
     * @param  array<string, mixed>  $data  carrier / tracking_no / fee
     *
     * @throws BusinessException
     */
    public function ship(Order $order, array $data): OrderShipment
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Shipped], true)) {
            // Paid → 首次发货；Shipped → 拆单加发
            throw new BusinessException('api.order_cannot_ship');
        }

        $carrier = trim((string) ($data['carrier'] ?? ''));
        $trackingNo = trim((string) ($data['tracking_no'] ?? ''));

        if ($carrier === '' || $trackingNo === '') {
            throw new BusinessException('api.shipment_carrier_tracking_required');
        }

        return DB::transaction(function () use ($order, $data, $carrier, $trackingNo) {
            $shipment = OrderShipment::create([
                'order_id' => $order->id,
                'carrier' => $carrier,
                'tracking_no' => $trackingNo,
                'status' => OrderShipmentStatus::Shipped,
                'shipped_at' => Carbon::now(),
                'fee' => isset($data['fee']) ? (float) $data['fee'] : 0,
                'raw_response' => isset($data['raw_response']) && is_array($data['raw_response'])
                    ? $data['raw_response'] : null,
            ]);

            // 首次发货：推进订单状态 + 同步老字段（兼容 OrderResource）
            if ($order->status === OrderStatus::Paid) {
                $this->orderService->transitionStatus($order, OrderStatus::Shipped);
            }
            $order->shipping_no = $trackingNo;
            $order->shipping_company = $carrier;
            if ($order->shipped_at === null) {
                $order->shipped_at = $shipment->shipped_at;
            }
            $order->save();

            return $shipment;
        });
    }

    /**
     * 更新物流单号 / 承运商。
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTracking(OrderShipment $shipment, array $data): void
    {
        $dirty = [];
        if (isset($data['carrier']) && is_string($data['carrier'])) {
            $dirty['carrier'] = trim($data['carrier']);
        }
        if (isset($data['tracking_no']) && is_string($data['tracking_no'])) {
            $dirty['tracking_no'] = trim($data['tracking_no']);
        }
        if (isset($data['fee'])) {
            $dirty['fee'] = (float) $data['fee'];
        }

        if ($dirty !== []) {
            $shipment->update($dirty);
        }
    }

    /**
     * 标记签收。
     *
     * 全部 shipment 都 delivered 时，订单整体转 Delivered。
     *
     * @throws BusinessException
     */
    public function markDelivered(OrderShipment $shipment): void
    {
        if (! $shipment->status->canTransitionTo(OrderShipmentStatus::Delivered)) {
            throw new BusinessException('api.shipment_cannot_deliver');
        }

        DB::transaction(function () use ($shipment) {
            $shipment->status = OrderShipmentStatus::Delivered;
            $shipment->delivered_at = Carbon::now();
            $shipment->save();

            $order = $shipment->order()->first();
            if (! $order instanceof Order) {
                return;
            }

            // 所有 shipment 全签收 → 订单整体 Delivered
            $allDelivered = $order->shipments()
                ->where('status', '!=', OrderShipmentStatus::Delivered->value)
                ->where('status', '!=', OrderShipmentStatus::Cancelled->value)
                ->doesntExist();

            if ($allDelivered && $order->status === OrderStatus::Shipped) {
                $this->orderService->transitionStatus($order, OrderStatus::Delivered);
            }
        });
    }

    /**
     * 取消某条 shipment（误发撤回）。
     *
     * @throws BusinessException
     */
    public function cancel(OrderShipment $shipment): void
    {
        if (! $shipment->status->canTransitionTo(OrderShipmentStatus::Cancelled)) {
            throw new BusinessException('api.shipment_cannot_cancel');
        }

        $shipment->status = OrderShipmentStatus::Cancelled;
        $shipment->save();
    }
}

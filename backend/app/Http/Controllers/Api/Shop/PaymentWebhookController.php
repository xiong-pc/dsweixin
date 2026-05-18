<?php

namespace App\Http\Controllers\Api\Shop;

use App\Contracts\Payment\WebhookResult;
use App\Enums\OrderPaymentStatus;
use App\Events\Mall\OrderPaidEvent;
use App\Http\Controllers\Api\Controller;
use App\Models\Mall\Order;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\PaymentMethod;
use App\Services\Api\Payment\PaymentManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 统一 webhook 入口。
 *
 * 路由：POST /api/v1/shop/payment/webhook/{paymentMethod}
 *   - {paymentMethod} = payment_methods.id（决定走哪个 driver + 用哪个 webhook_secret）
 *
 * 流程：
 *   1. 解析 PaymentMethod → 构造 driver
 *   2. driver.handleWebhook() 验签 + 归一化事件
 *   3. 根据 WebhookResult 写 OrderPayment（transaction_id UNIQUE 防重）
 *   4. 首次成功 → dispatch OrderPaidEvent（HandleOrderPaidListener 推进订单状态）
 *
 * 即使签名失败 / 未知事件，也返回 200 避免第三方重复重试导致雪崩；
 * 真正的错误用日志 + WebhookResult.message 标记。
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentManager $paymentManager,
    ) {}

    public function handle(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $driver = $this->paymentManager->makeDriver($paymentMethod);
        $result = $driver->handleWebhook($request);

        if (! $result->success) {
            // 验签失败 / 不可解析的事件：记录但仍返回 200，避免第三方重试风暴
            return response()->json([
                'code' => 200,
                'msg' => 'ignored',
                'reason' => $result->message,
            ]);
        }

        return match ($result->eventType) {
            WebhookResult::EVENT_PAYMENT_SUCCESS => $this->onPaymentSuccess($paymentMethod, $result),
            WebhookResult::EVENT_PAYMENT_FAILED => $this->onPaymentFailed($paymentMethod, $result),
            WebhookResult::EVENT_REFUND_COMPLETED => $this->onRefundCompleted($paymentMethod, $result),
            default => response()->json(['code' => 200, 'msg' => 'unhandled']),
        };
    }

    private function onPaymentSuccess(PaymentMethod $method, WebhookResult $result): JsonResponse
    {
        $order = $this->resolveOrder((int) $method->tenant_id, $result);
        if ($order === null) {
            return response()->json(['code' => 200, 'msg' => 'order_not_found']);
        }

        // 幂等：transaction_id 唯一约束兜底，先查再插，并发下用 catch QueryException 兜住
        $existing = OrderPayment::query()
            ->where('transaction_id', $result->transactionId)
            ->first();

        if ($existing !== null) {
            return response()->json(['code' => 200, 'msg' => 'duplicate']);
        }

        try {
            $payment = DB::transaction(function () use ($order, $method, $result) {
                return OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_method' => (string) $method->code,
                    'transaction_id' => $result->transactionId,
                    'amount' => $result->amount,
                    'currency' => (string) $order->currency,
                    'status' => OrderPaymentStatus::Success,
                    'paid_at' => now(),
                    'raw_response' => $result->raw,
                ]);
            });
        } catch (QueryException $e) {
            // 并发下唯一约束抛错 → 当作幂等冲突
            return response()->json(['code' => 200, 'msg' => 'duplicate']);
        }

        OrderPaidEvent::dispatch($order->fresh() ?? $order, $payment);

        return response()->json(['code' => 200, 'msg' => 'ok']);
    }

    private function onPaymentFailed(PaymentMethod $method, WebhookResult $result): JsonResponse
    {
        $order = $this->resolveOrder((int) $method->tenant_id, $result);
        if ($order === null) {
            return response()->json(['code' => 200, 'msg' => 'order_not_found']);
        }

        // 记录失败流水（去重靠 transaction_id UNIQUE）
        try {
            OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => (string) $method->code,
                'transaction_id' => $result->transactionId,
                'amount' => $result->amount,
                'currency' => (string) $order->currency,
                'status' => OrderPaymentStatus::Failed,
                'raw_response' => $result->raw,
            ]);
        } catch (QueryException) {
            // 重复忽略
        }

        return response()->json(['code' => 200, 'msg' => 'ok']);
    }

    private function onRefundCompleted(PaymentMethod $method, WebhookResult $result): JsonResponse
    {
        // PR27 完整退款链路尚未到位，这里仅记录原始事件
        return response()->json(['code' => 200, 'msg' => 'refund_received', 'tx' => $result->transactionId]);
    }

    private function resolveOrder(int $tenantId, WebhookResult $result): ?Order
    {
        if ($result->orderNo === null || $result->orderNo === '') {
            return null;
        }

        return Order::query()
            ->where('tenant_id', $tenantId)
            ->where('order_no', $result->orderNo)
            ->first();
    }
}

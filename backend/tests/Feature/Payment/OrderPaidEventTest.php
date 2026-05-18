<?php

namespace Tests\Feature\Payment;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Events\Mall\OrderPaidEvent;
use App\Models\Mall\Order;
use App\Models\Mall\OrderPayment;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Shop\CartService;
use App\Services\Api\Shop\OrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * OrderPaidEvent → HandleOrderPaidListener 端到端测试。
 *
 * 验证 spec 承诺：支付成功 → 订单状态变 paid → 库存扣减 → 通知。
 * Listener 已在 AppServiceProvider::boot() 注册，本测试用 Event::dispatch 触发。
 */
class OrderPaidEventTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'op-'.uniqid(),
            'name' => 'Order Paid Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'sku_prefix' => 'P-PAID',
            'base_currency' => 'CNY',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-PAID',
            'price' => 100.0,
            'stock' => 10,
        ]);
    }

    /**
     * 走 cart → place-order 流程产生一个待支付订单，库存预占 N 件。
     */
    private function createPendingOrder(int $quantity = 2): Order
    {
        /** @var CartService $cartService */
        $cartService = app(CartService::class);
        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        $cart = $cartService->resolveOrCreate(
            $this->tenantId, null, null, 'guest-paid'
        );
        $cartService->addItem($cart, $this->variant->id, $quantity);

        return $orderService->createFromCart($cart, [
            'country_code' => 'CN',
            'street' => 'X',
            'contact_name' => 'T',
            'contact_phone' => '13800138000',
        ]);
    }

    private function createPayment(Order $order, OrderPaymentStatus $status, string $txn = 'TX-001'): OrderPayment
    {
        return OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => 'stripe',
            'transaction_id' => $txn,
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => $status,
            'paid_at' => now(),
            'raw_response' => ['source' => 'test'],
        ]);
    }

    public function test_success_event_marks_order_paid_and_deducts_stock(): void
    {
        $order = $this->createPendingOrder(2);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(2, (int) $this->variant->fresh()->reserved);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);

        $payment = $this->createPayment($order, OrderPaymentStatus::Success);
        OrderPaidEvent::dispatch($order, $payment);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Paid, $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame('stripe', $fresh->pay_method);

        // 库存：reserved 释放、stock 真扣减
        $variantFresh = $this->variant->fresh();
        $this->assertSame(0, (int) $variantFresh->reserved);
        $this->assertSame(8, (int) $variantFresh->stock);
    }

    public function test_pending_payment_does_not_change_order(): void
    {
        $order = $this->createPendingOrder(2);
        $payment = $this->createPayment($order, OrderPaymentStatus::Pending);

        OrderPaidEvent::dispatch($order, $payment);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Pending, $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertSame(2, (int) $this->variant->fresh()->reserved);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_failed_payment_does_not_change_order(): void
    {
        $order = $this->createPendingOrder(2);
        $payment = $this->createPayment($order, OrderPaymentStatus::Failed);

        OrderPaidEvent::dispatch($order, $payment);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Pending, $fresh->status);
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_double_dispatch_is_idempotent(): void
    {
        $order = $this->createPendingOrder(3);
        $payment = $this->createPayment($order, OrderPaymentStatus::Success, 'TX-DUP');

        OrderPaidEvent::dispatch($order, $payment);
        // 第二次：order 已 Paid，listener 应直接 return（库存不会被扣两次）
        OrderPaidEvent::dispatch($order->fresh(), $payment);

        $variantFresh = $this->variant->fresh();
        $this->assertSame(7, (int) $variantFresh->stock);   // 10 - 3 一次
        $this->assertSame(0, (int) $variantFresh->reserved);
    }

    public function test_transaction_id_is_globally_unique_at_db_level(): void
    {
        $order = $this->createPendingOrder(1);
        $this->createPayment($order, OrderPaymentStatus::Success, 'TX-UNIQUE');

        $this->expectException(QueryException::class);
        $this->createPayment($order, OrderPaymentStatus::Success, 'TX-UNIQUE');
    }

    public function test_listener_logs_payment_info(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $channel, array $context) {
                return $channel === 'mall.order.paid'
                    && isset($context['order_no'])
                    && isset($context['transaction_id'])
                    && $context['transaction_id'] === 'TX-LOG'
                    && $context['payment_method'] === 'stripe';
            });

        $order = $this->createPendingOrder(1);
        $payment = $this->createPayment($order, OrderPaymentStatus::Success, 'TX-LOG');

        OrderPaidEvent::dispatch($order, $payment);
    }

    public function test_already_paid_order_skip_relisten(): void
    {
        // 手工把订单转到 paid（绕过事件），再 dispatch 不应叠加扣库存
        $order = $this->createPendingOrder(2);
        $orderService = app(OrderService::class);
        $orderService->confirmPayment($order, 'manual');

        $variantBefore = $this->variant->fresh();
        $this->assertSame(8, (int) $variantBefore->stock);
        $this->assertSame(0, (int) $variantBefore->reserved);

        $payment = $this->createPayment($order->fresh(), OrderPaymentStatus::Success, 'TX-LATE');
        OrderPaidEvent::dispatch($order->fresh(), $payment);

        // listener 应 short-circuit：库存不变
        $variantAfter = $this->variant->fresh();
        $this->assertSame(8, (int) $variantAfter->stock);
        $this->assertSame(0, (int) $variantAfter->reserved);
    }

    public function test_order_payment_status_enum_cast(): void
    {
        $order = $this->createPendingOrder(1);
        $payment = $this->createPayment($order, OrderPaymentStatus::Success, 'TX-ENUM');

        $fresh = OrderPayment::find($payment->id);
        $this->assertInstanceOf(OrderPaymentStatus::class, $fresh->status);
        $this->assertSame(OrderPaymentStatus::Success, $fresh->status);
        $this->assertTrue($fresh->isSuccess());
    }
}

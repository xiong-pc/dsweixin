<?php

namespace Tests\Feature\Mall;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Models\Mall\OrderHistory;
use App\Models\Mall\OrderItem;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Shop\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 验证 OrderObserver 自动写 order_histories：
 *   - status 变更 → 写一条 history
 *   - 非 status 字段变更 → 不写
 *   - transitionStatus 携带 reason/operator → 透传到 history
 *   - 多次状态切换 → 多条 history（顺序正确）
 *   - 非法状态转移 → 抛异常 + 不写 history
 */
class OrderHistoryAutoLogTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'oh-'.uniqid(),
            'name' => 'History Tenant',
            'status' => 1,
            'primary_domain' => uniqid().'.example.com',
        ]);
        $this->tenantId = $tenant->id;
    }

    private function makePendingOrder(int $qty = 2): Order
    {
        $product = Product::create(['tenant_id' => $this->tenantId, 'base_currency' => 'CNY']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'price' => 50,
            'stock' => 10,
            'reserved' => $qty,
        ]);
        $order = Order::create([
            'order_no' => 'O-'.uniqid(),
            'tenant_id' => $this->tenantId,
            'currency' => 'CNY',
            'subtotal' => 50 * $qty,
            'total' => 50 * $qty,
            'status' => OrderStatus::Pending,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name_snapshot' => 'X',
            'unit_price' => 50,
            'currency' => 'CNY',
            'quantity' => $qty,
            'line_total' => 50 * $qty,
        ]);

        return $order;
    }

    public function test_status_change_writes_one_history_row(): void
    {
        $order = $this->makePendingOrder();

        app(OrderService::class)->transitionStatus($order, OrderStatus::Paid);

        $this->assertSame(1, OrderHistory::query()->count());
        $row = OrderHistory::query()->first();
        $this->assertNotNull($row);
        $this->assertSame($order->id, (int) $row->order_id);
        $this->assertSame('pending', $row->from_status);
        $this->assertSame('paid', $row->to_status);
        $this->assertSame('system', $row->operator_type);
        $this->assertSame(0, (int) $row->operator_id);
    }

    public function test_non_status_field_update_does_not_log(): void
    {
        $order = $this->makePendingOrder();

        $order->remark = 'updated remark';
        $order->save();

        $this->assertSame(0, OrderHistory::query()->count());
    }

    public function test_multiple_transitions_are_logged_in_order(): void
    {
        $order = $this->makePendingOrder();
        $service = app(OrderService::class);

        $service->transitionStatus($order, OrderStatus::Paid);
        $service->transitionStatus($order, OrderStatus::Shipped);
        $service->transitionStatus($order, OrderStatus::Delivered);

        $rows = OrderHistory::query()->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(['pending', 'paid', 'shipped'], $rows->pluck('from_status')->all());
        $this->assertSame(['paid', 'shipped', 'delivered'], $rows->pluck('to_status')->all());
    }

    public function test_context_reason_and_operator_propagate_to_history(): void
    {
        $order = $this->makePendingOrder();

        app(OrderService::class)->transitionStatus($order, OrderStatus::Cancelled, [
            'reason' => 'customer_request',
            'note' => '用户主动取消',
            'operator_type' => 'customer',
            'operator_id' => 42,
        ]);

        $row = OrderHistory::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('customer_request', $row->reason);
        $this->assertSame('用户主动取消', $row->note);
        $this->assertSame('customer', $row->operator_type);
        $this->assertSame(42, (int) $row->operator_id);
    }

    public function test_invalid_transition_throws_and_writes_no_history(): void
    {
        $order = $this->makePendingOrder();

        try {
            app(OrderService::class)->transitionStatus($order, OrderStatus::Delivered);
            $this->fail('Expected BusinessException');
        } catch (BusinessException $e) {
            $this->assertSame('api.invalid_order_status_transition', $e->getMessage());
        }

        $this->assertSame(0, OrderHistory::query()->count());
        // 订单状态保持 pending
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_history_relation_returns_rows(): void
    {
        $order = $this->makePendingOrder();
        $service = app(OrderService::class);

        $service->transitionStatus($order, OrderStatus::Paid);
        $service->transitionStatus($order, OrderStatus::Shipped);

        $histories = $order->fresh()->histories;
        $this->assertCount(2, $histories);
        $this->assertSame('paid', $histories[0]->to_status);
        $this->assertSame('shipped', $histories[1]->to_status);
    }

    public function test_transient_context_cleared_after_save(): void
    {
        $order = $this->makePendingOrder();

        // 第一次带 reason
        app(OrderService::class)->transitionStatus($order, OrderStatus::Paid, [
            'reason' => 'webhook_payment',
        ]);

        // 第二次不带 → 历史记录的 reason 应为空，不应继承上一次的 reason
        app(OrderService::class)->transitionStatus($order, OrderStatus::Shipped);

        $rows = OrderHistory::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('webhook_payment', $rows[0]->reason);
        $this->assertSame('', $rows[1]->reason);
    }

    public function test_operator_id_accepts_string_digit(): void
    {
        $order = $this->makePendingOrder();

        app(OrderService::class)->transitionStatus($order, OrderStatus::Paid, [
            'operator_id' => '99',  // 模拟从 request 来的字符串
        ]);

        $row = OrderHistory::query()->first();
        $this->assertSame(99, (int) $row->operator_id);
    }

    public function test_history_has_no_updated_at(): void
    {
        $order = $this->makePendingOrder();
        app(OrderService::class)->transitionStatus($order, OrderStatus::Paid);

        $row = OrderHistory::query()->first();
        // updated_at 字段不存在 / 不写
        $this->assertNull($row->updated_at ?? null);
        $this->assertNotNull($row->created_at);
    }
}

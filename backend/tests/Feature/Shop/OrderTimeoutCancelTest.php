<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Jobs\Mall\ReleaseExpiredOrderReservationsJob;
use App\Models\Mall\Order;
use App\Models\Mall\Product;
use App\Models\Mall\ProductVariant;
use App\Models\Tenant;
use App\Services\Api\Shop\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderTimeoutCancelTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'code' => 'test', 'name' => 'Test', 'status' => 1,
            'primary_domain' => 'test.example.com',
        ]);
        $this->tenantId = $tenant->id;

        $product = Product::create(['tenant_id' => $this->tenantId]);
        $product->translations()->create(['locale' => 'zh-CN', 'name' => '商品']);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id, 'sku' => 'V1', 'price' => 100, 'stock' => 50,
        ]);
    }

    private function headers(): array
    {
        return [
            'X-Tenant-Id' => (string) $this->tenantId,
            'X-Session-Id' => 'g',
        ];
    }

    private function defaultAddress(): array
    {
        return [
            'country_code' => 'CN', 'street' => 'X',
            'contact_name' => 'T', 'contact_phone' => '13800138000',
        ];
    }

    private function placeOrder(int $qty = 2): int
    {
        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => $qty,
        ], $h)->assertOk();

        $resp = $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h)->assertOk();

        return (int) $resp->json('data.id');
    }

    public function test_placing_order_reserves_stock(): void
    {
        $this->placeOrder(3);

        $this->assertSame(50, $this->variant->fresh()->stock);
        $this->assertSame(3, $this->variant->fresh()->reserved);
    }

    public function test_placing_order_with_insufficient_stock_returns_error(): void
    {
        $this->variant->update(['stock' => 1]);

        $h = $this->headers();
        $this->postJson('/api/v1/shop/cart/items', [
            'variant_id' => $this->variant->id, 'quantity' => 5,
        ], $h)->assertOk();

        $this->postJson('/api/v1/shop/orders', [
            'shipping_address' => $this->defaultAddress(),
        ], $h)->assertStatus(400);

        // 库存未被预占
        $this->assertSame(0, $this->variant->fresh()->reserved);
    }

    public function test_cancel_order_releases_reserved_stock(): void
    {
        $orderId = $this->placeOrder(4);
        $this->assertSame(4, $this->variant->fresh()->reserved);

        $order = Order::find($orderId);
        app(OrderService::class)->cancelOrder($order);

        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(50, $this->variant->fresh()->stock);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    public function test_confirm_payment_deducts_real_stock(): void
    {
        $orderId = $this->placeOrder(5);

        $order = Order::find($orderId);
        app(OrderService::class)->confirmPayment($order, 'stripe');

        $this->assertSame(45, $this->variant->fresh()->stock);    // 50 - 5
        $this->assertSame(0, $this->variant->fresh()->reserved);  // 5 - 5
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('stripe', $order->fresh()->pay_method);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_expired_pending_order_is_cancelled_by_job(): void
    {
        $orderId = $this->placeOrder(2);

        // 把订单 created_at 改到 31 分钟前
        Order::where('id', $orderId)->update(['created_at' => Carbon::now()->subMinutes(31)]);

        ReleaseExpiredOrderReservationsJob::dispatchSync(30);

        $order = Order::find($orderId);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(0, $this->variant->fresh()->reserved);
    }

    public function test_recent_pending_order_not_cancelled_by_job(): void
    {
        $orderId = $this->placeOrder(2);

        // 25 分钟前，未到 30 min 阈值
        Order::where('id', $orderId)->update(['created_at' => Carbon::now()->subMinutes(25)]);

        ReleaseExpiredOrderReservationsJob::dispatchSync(30);

        $order = Order::find($orderId);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(2, $this->variant->fresh()->reserved);
    }

    public function test_paid_order_not_cancelled_even_if_old(): void
    {
        $orderId = $this->placeOrder(2);
        $order = Order::find($orderId);
        app(OrderService::class)->confirmPayment($order, 'stripe');

        // 即使时间很久也不应再次取消
        Order::where('id', $orderId)->update(['created_at' => Carbon::now()->subDays(7)]);

        ReleaseExpiredOrderReservationsJob::dispatchSync(30);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(48, $this->variant->fresh()->stock); // 没有变化
    }

    public function test_artisan_command_runs_release_job(): void
    {
        $orderId = $this->placeOrder(2);
        Order::where('id', $orderId)->update(['created_at' => Carbon::now()->subMinutes(31)]);

        $exitCode = $this->artisan('mall:release-expired-reservations', ['--minutes' => 30])
            ->assertExitCode(0)
            ->run();

        $this->assertSame(0, $exitCode);

        $order = Order::find($orderId);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
    }

    public function test_invalid_status_transition_throws(): void
    {
        $orderId = $this->placeOrder(1);
        $order = Order::find($orderId);
        app(OrderService::class)->confirmPayment($order, 'stripe');

        // Paid -> Pending 不允许
        $this->expectException(BusinessException::class);
        app(OrderService::class)->transitionStatus($order->fresh(), OrderStatus::Pending);
    }
}

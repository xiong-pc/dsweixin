<?php

namespace Tests\Unit\Mall;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Mall\Order;
use App\Services\Api\Mall\OrderStateMachine;
use PHPUnit\Framework\TestCase;

/**
 * OrderStateMachine 纯逻辑单元测试（不加载 Laravel app / DB）。
 *
 * 状态转移图：
 *   pending → paid → shipped → delivered
 *           ↓        ↓        ↓
 *        cancelled refunded refunded
 */
class OrderStateMachineTest extends TestCase
{
    private OrderStateMachine $sm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sm = new OrderStateMachine;
    }

    public function test_pending_can_transition_to_paid_or_cancelled(): void
    {
        $this->assertTrue($this->sm->canTransition(OrderStatus::Pending, OrderStatus::Paid));
        $this->assertTrue($this->sm->canTransition(OrderStatus::Pending, OrderStatus::Cancelled));
    }

    public function test_pending_cannot_jump_to_shipped_or_delivered(): void
    {
        $this->assertFalse($this->sm->canTransition(OrderStatus::Pending, OrderStatus::Shipped));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Pending, OrderStatus::Delivered));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Pending, OrderStatus::Refunded));
    }

    public function test_paid_can_transition_to_shipped_cancelled_or_refunded(): void
    {
        $this->assertTrue($this->sm->canTransition(OrderStatus::Paid, OrderStatus::Shipped));
        $this->assertTrue($this->sm->canTransition(OrderStatus::Paid, OrderStatus::Cancelled));
        $this->assertTrue($this->sm->canTransition(OrderStatus::Paid, OrderStatus::Refunded));
    }

    public function test_paid_cannot_go_back_to_pending(): void
    {
        $this->assertFalse($this->sm->canTransition(OrderStatus::Paid, OrderStatus::Pending));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Paid, OrderStatus::Delivered));
    }

    public function test_shipped_can_transition_to_delivered_or_refunded(): void
    {
        $this->assertTrue($this->sm->canTransition(OrderStatus::Shipped, OrderStatus::Delivered));
        $this->assertTrue($this->sm->canTransition(OrderStatus::Shipped, OrderStatus::Refunded));
    }

    public function test_shipped_cannot_go_back(): void
    {
        $this->assertFalse($this->sm->canTransition(OrderStatus::Shipped, OrderStatus::Pending));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Shipped, OrderStatus::Paid));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Shipped, OrderStatus::Cancelled));
    }

    public function test_delivered_can_only_refund(): void
    {
        $this->assertTrue($this->sm->canTransition(OrderStatus::Delivered, OrderStatus::Refunded));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Delivered, OrderStatus::Pending));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Delivered, OrderStatus::Paid));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Delivered, OrderStatus::Shipped));
        $this->assertFalse($this->sm->canTransition(OrderStatus::Delivered, OrderStatus::Cancelled));
    }

    public function test_terminal_states_have_no_transitions(): void
    {
        foreach ([OrderStatus::Cancelled, OrderStatus::Refunded] as $terminal) {
            foreach (OrderStatus::cases() as $any) {
                if ($any === $terminal) {
                    continue;
                }
                $this->assertFalse(
                    $this->sm->canTransition($terminal, $any),
                    "Terminal {$terminal->value} should not transition to {$any->value}"
                );
            }
        }
    }

    public function test_assert_can_transition_throws_on_invalid(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('api.invalid_order_status_transition');
        $this->sm->assertCanTransition(OrderStatus::Pending, OrderStatus::Shipped);
    }

    public function test_assert_can_transition_silent_on_valid(): void
    {
        $this->sm->assertCanTransition(OrderStatus::Pending, OrderStatus::Paid);
        $this->assertTrue(true); // 没抛即通过
    }

    public function test_next_states_returns_enum_values(): void
    {
        $order = new Order;
        $order->status = OrderStatus::Paid;

        $this->assertEqualsCanonicalizing(
            ['shipped', 'cancelled', 'refunded'],
            $this->sm->nextStates($order),
        );
    }

    public function test_next_states_for_terminal_is_empty(): void
    {
        $order = new Order;
        $order->status = OrderStatus::Refunded;
        $this->assertSame([], $this->sm->nextStates($order));

        $order->status = OrderStatus::Cancelled;
        $this->assertSame([], $this->sm->nextStates($order));
    }
}

<?php

namespace App\Jobs\Mall;

use App\Enums\OrderStatus;
use App\Models\Mall\Order;
use App\Services\Api\Shop\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * 扫描超过预占时长（默认 30 min）仍未支付的订单，自动取消并释放库存。
 */
class ReleaseExpiredOrderReservationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $minutes = 30) {}

    public function handle(OrderService $orderService): void
    {
        $threshold = Carbon::now()->subMinutes($this->minutes);

        Order::where('status', OrderStatus::Pending->value)
            ->where('created_at', '<=', $threshold)
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($orderService) {
                /** @var Collection<int, Order> $orders */
                foreach ($orders as $order) {
                    /** @var Order $order */
                    $orderService->cancelOrder($order);
                }
            });
    }
}

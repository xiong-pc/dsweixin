<?php

namespace App\Http\Controllers\Api\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\Shop\OrderResource;
use App\Models\Mall\Customer;
use App\Models\Mall\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 我的订单（仅当前已登录 customer 可见，与游客 X-Session-Id 链路无关）。
 *
 * 与 Shop\OrderController::index 的区别：身份强制来自 passport-customer guard 解出的 Customer，
 * 客户端无法通过 X-Customer-Id header 仿冒查看他人订单。
 */
class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $query = Order::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->with(['items', 'shippingAddress', 'shipments']);

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
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
        $customer = $this->requireCustomer($request);

        if ((int) $order->tenant_id !== (int) $customer->tenant_id) {
            abort(403);
        }
        if ((int) ($order->customer_id ?? 0) !== (int) $customer->id) {
            abort(403);
        }

        return $this->success(new OrderResource(
            $order->load(['items', 'shippingAddress', 'billingAddress', 'shipments'])
        ));
    }

    private function requireCustomer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            throw new BusinessException('api.unauthorized', 401);
        }

        return $user;
    }
}

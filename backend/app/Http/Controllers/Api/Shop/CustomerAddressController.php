<?php

namespace App\Http\Controllers\Api\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Shop\CustomerAddress\CreateCustomerAddressRequest;
use App\Http\Requests\Api\Shop\CustomerAddress\UpdateCustomerAddressRequest;
use App\Http\Resources\Api\Shop\CustomerAddressResource;
use App\Models\Mall\Customer;
use App\Models\Mall\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 客户地址簿 CRUD（仅当前已登录 customer 可见）。
 *
 * 默认地址语义：同一 customer 仅一条 is_default=1；create/update 指定 default 时自动降级其它；
 * 首条地址自动 default；删除 default 后自动晋升下一条。
 */
class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $addresses = CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return $this->success(CustomerAddressResource::collection($addresses));
    }

    public function store(CreateCustomerAddressRequest $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        $address = DB::transaction(function () use ($customer, $data, $isDefault) {
            $hasAny = CustomerAddress::where('customer_id', $customer->id)->exists();

            if ($isDefault || ! $hasAny) {
                CustomerAddress::where('customer_id', $customer->id)
                    ->where('is_default', 1)
                    ->update(['is_default' => 0]);
            }

            $data['customer_id'] = $customer->id;
            $data['is_default'] = ($isDefault || ! $hasAny) ? 1 : 0;

            return CustomerAddress::create($data);
        });

        return $this->success(new CustomerAddressResource($address), 'api.created');
    }

    public function show(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        return $this->success(new CustomerAddressResource($address));
    }

    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);
        $data = $request->validated();

        DB::transaction(function () use ($address, $data) {
            if (array_key_exists('is_default', $data) && (bool) $data['is_default']) {
                CustomerAddress::where('customer_id', $address->customer_id)
                    ->where('id', '!=', $address->id)
                    ->where('is_default', 1)
                    ->update(['is_default' => 0]);
                $data['is_default'] = 1;
            } elseif (array_key_exists('is_default', $data)) {
                // 不允许通过 update 把自己从 default 降级为 0（会留下无 default 状态），
                // 除非客户端通过新增/切换另一条为 default 来隐式覆盖。
                unset($data['is_default']);
            }

            $address->fill($data)->save();
        });

        return $this->success(new CustomerAddressResource($address->refresh()));
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->ensureOwnership($request, $address);

        DB::transaction(function () use ($address) {
            $wasDefault = (int) $address->is_default === 1;
            $customerId = (int) $address->customer_id;
            $address->delete();

            if ($wasDefault) {
                // 晋升下一条（按 id 最大者，等价于最新添加）
                $next = CustomerAddress::where('customer_id', $customerId)
                    ->orderByDesc('id')
                    ->first();
                if ($next !== null) {
                    $next->forceFill(['is_default' => 1])->save();
                }
            }
        });

        return $this->success(null, 'api.deleted');
    }

    private function requireCustomer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            throw new BusinessException('api.unauthorized', 401);
        }

        return $user;
    }

    private function ensureOwnership(Request $request, CustomerAddress $address): void
    {
        $customer = $this->requireCustomer($request);
        if ((int) $address->customer_id !== (int) $customer->id) {
            abort(403);
        }
    }
}

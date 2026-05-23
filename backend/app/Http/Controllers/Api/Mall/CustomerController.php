<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Customer\UpdateCustomerRequest;
use App\Http\Resources\Api\Mall\CustomerResource;
use App\Models\Mall\Customer;
use App\Services\Api\Mall\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 后台客户管理（admin）。
 *
 * 路由：GET/PUT/DELETE /api/v1/mall/customers
 *
 * 不开放 store：客户由前台自助注册（M09-PR35）。
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['keywords', 'status', 'group_id', 'shop_id']);

        if ($user !== null && $user->isSuperAdmin() !== true) {
            $filters['tenant_id'] = (int) $user->tenant_id;
        } elseif ($request->filled('tenant_id')) {
            $filters['tenant_id'] = (int) $request->input('tenant_id');
        }

        return $this->paginate(
            $this->service->list(
                $filters,
                (int) $request->input('pageSize', 20),
                (int) $request->input('pageNum', 1)
            ),
            CustomerResource::class
        );
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureTenantAccess($request, $customer);

        return $this->success(
            new CustomerResource($customer->load('group.translations'))
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->ensureTenantAccess($request, $customer);

        $this->service->update($customer, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureTenantAccess($request, $customer);

        $this->service->delete($customer);

        return $this->success(null, 'api.deleted');
    }

    private function ensureTenantAccess(Request $request, Customer $customer): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $customer->tenant_id) {
            abort(403);
        }
    }
}

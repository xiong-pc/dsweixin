<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\CustomerGroup\StoreCustomerGroupRequest;
use App\Http\Requests\Api\Mall\CustomerGroup\UpdateCustomerGroupRequest;
use App\Http\Resources\Api\Mall\CustomerGroupResource;
use App\Models\Mall\CustomerGroup;
use App\Services\Api\Mall\CustomerGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function __construct(private readonly CustomerGroupService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['keywords', 'status']);

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
            CustomerGroupResource::class
        );
    }

    public function store(StoreCustomerGroupRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $group = $this->service->create($tenantId, $request->validated());

        return $this->success(new CustomerGroupResource($group), 'api.created');
    }

    public function show(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $this->ensureTenantAccess($request, $customerGroup);

        return $this->success(
            new CustomerGroupResource($customerGroup->load('translations'))
        );
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): JsonResponse
    {
        $this->ensureTenantAccess($request, $customerGroup);

        $this->service->update($customerGroup, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $this->ensureTenantAccess($request, $customerGroup);

        $this->service->delete($customerGroup);

        return $this->success(null, 'api.deleted');
    }

    private function resolveTenantId(Request $request): int
    {
        $user = $request->user();
        if ($user !== null && $user->isSuperAdmin() === true) {
            $forced = $request->input('tenant_id');

            return $forced !== null ? (int) $forced : (int) ($user->tenant_id ?? 0);
        }

        return (int) ($user->tenant_id ?? 0);
    }

    private function ensureTenantAccess(Request $request, CustomerGroup $group): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $group->tenant_id) {
            abort(403);
        }
    }
}

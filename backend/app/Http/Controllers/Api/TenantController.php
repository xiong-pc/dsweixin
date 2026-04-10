<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Tenant\StoreTenantRequest;
use App\Http\Requests\Api\Tenant\UpdateTenantRequest;
use App\Http\Resources\Api\TenantResource;
use App\Models\Tenant;
use App\Services\Api\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasTenantManagementMenu()) {
            return $this->error('api.forbidden', 403);
        }

        return $this->paginate(
            $this->service->list(
                $request->user(),
                $request->only(['keywords', 'name', 'status']),
                (int) $request->input('pageSize', 10),
                (int) $request->input('pageNum', 1)
            ),
            TenantResource::class
        );
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->service->create(
            $request->only(['name', 'code', 'status', 'contact_name', 'contact_phone', 'expired_at', 'remark'])
        );

        return $this->success(new TenantResource($tenant), 'api.created');
    }

    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        if (!$request->user()->hasTenantManagementMenu()) {
            return $this->error('api.forbidden', 403);
        }

        if (!$request->user()->isSuperAdmin()
            && (int) $tenant->id !== (int) $request->user()->tenant_id) {
            return $this->error('api.forbidden', 403);
        }

        return $this->success(new TenantResource($tenant));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $this->service->update(
            $tenant,
            $request->only(['name', 'code', 'status', 'contact_name', 'contact_phone', 'expired_at', 'remark'])
        );

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($tenant);

        return $this->success(null, 'api.deleted');
    }
}

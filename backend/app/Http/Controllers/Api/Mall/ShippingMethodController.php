<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\ShippingMethod\StoreShippingMethodRequest;
use App\Http\Requests\Api\Mall\ShippingMethod\UpdateShippingMethodRequest;
use App\Http\Resources\Api\Mall\ShippingMethodResource;
use App\Models\Mall\ShippingMethod;
use App\Services\Api\Mall\ShippingMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function __construct(private readonly ShippingMethodService $service) {}

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
            ShippingMethodResource::class
        );
    }

    public function store(StoreShippingMethodRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $method = $this->service->create($tenantId, $request->validated());

        return $this->success(new ShippingMethodResource($method), 'api.created');
    }

    public function show(Request $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $this->ensureTenantAccess($request, $shippingMethod);

        return $this->success(
            new ShippingMethodResource($shippingMethod->load(['translations', 'rates']))
        );
    }

    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $this->ensureTenantAccess($request, $shippingMethod);

        $this->service->update($shippingMethod, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, ShippingMethod $shippingMethod): JsonResponse
    {
        $this->ensureTenantAccess($request, $shippingMethod);

        $this->service->delete($shippingMethod);

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

    private function ensureTenantAccess(Request $request, ShippingMethod $method): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $method->tenant_id) {
            abort(403);
        }
    }
}

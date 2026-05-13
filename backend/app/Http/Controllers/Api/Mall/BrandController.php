<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Brand\StoreBrandRequest;
use App\Http\Requests\Api\Mall\Brand\UpdateBrandRequest;
use App\Http\Resources\Api\Mall\BrandResource;
use App\Models\Mall\Brand;
use App\Services\Api\Mall\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $service) {}

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
            BrandResource::class
        );
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $brand = $this->service->create($tenantId, $request->validated());

        return $this->success(new BrandResource($brand), 'api.created');
    }

    public function show(Request $request, Brand $brand): JsonResponse
    {
        $this->ensureTenantAccess($request, $brand);

        return $this->success(new BrandResource($brand->load('translations')));
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $this->ensureTenantAccess($request, $brand);

        $this->service->update($brand, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Brand $brand): JsonResponse
    {
        $this->ensureTenantAccess($request, $brand);

        $this->service->delete($brand);

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

    private function ensureTenantAccess(Request $request, Brand $brand): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $brand->tenant_id) {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Category\StoreCategoryRequest;
use App\Http\Requests\Api\Mall\Category\UpdateCategoryRequest;
use App\Http\Resources\Api\Mall\CategoryResource;
use App\Models\Mall\Category;
use App\Services\Api\Mall\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    /**
     * 树形列表（不分页）。
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['keywords', 'status']);

        if ($user !== null && $user->isSuperAdmin() !== true) {
            $filters['tenant_id'] = (int) $user->tenant_id;
        } elseif ($request->filled('tenant_id')) {
            $filters['tenant_id'] = (int) $request->input('tenant_id');
        }

        return $this->success($this->service->tree($filters));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $category = $this->service->create($tenantId, $request->validated());

        return $this->success(new CategoryResource($category), 'api.created');
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        $this->ensureTenantAccess($request, $category);

        return $this->success(new CategoryResource($category->load('translations')));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->ensureTenantAccess($request, $category);

        $this->service->update($category, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->ensureTenantAccess($request, $category);

        $this->service->delete($category);

        return $this->success(null, 'api.deleted');
    }

    /**
     * 拖拽排序：批量更新 parent_id + sort。
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|min:1',
            'items.*.parent_id' => 'nullable|integer|min:0',
            'items.*.sort' => 'nullable|integer',
        ]);

        $tenantId = $this->resolveTenantId($request);

        $this->service->reorder($tenantId, $request->input('items', []));

        return $this->success(null, 'api.updated');
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

    private function ensureTenantAccess(Request $request, Category $category): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $category->tenant_id) {
            abort(403);
        }
    }
}

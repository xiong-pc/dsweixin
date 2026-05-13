<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Specification\StoreSpecificationRequest;
use App\Http\Requests\Api\Mall\Specification\UpdateSpecificationRequest;
use App\Http\Resources\Api\Mall\SpecificationResource;
use App\Models\Mall\Specification;
use App\Services\Api\Mall\SpecificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpecificationController extends Controller
{
    public function __construct(private readonly SpecificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['keywords', 'status']);

        // 非超管自动按 tenant 过滤
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
            SpecificationResource::class
        );
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreSpecificationRequest $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->resolveTenantId($request);

        // code 同租户内唯一校验（运行时校验，避免 unique rule 写死租户）
        $exists = Specification::where('tenant_id', $tenantId)
            ->where('code', $request->validated('code'))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
        }

        $spec = $this->service->create($tenantId, $request->validated());

        return $this->success(new SpecificationResource($spec), 'api.created');
    }

    public function show(Request $request, Specification $specification): JsonResponse
    {
        $this->ensureTenantAccess($request, $specification);

        return $this->success(new SpecificationResource(
            $specification->load(['translations', 'values.translations'])
        ));
    }

    /**
     * @throws ValidationException
     */
    public function update(UpdateSpecificationRequest $request, Specification $specification): JsonResponse
    {
        $this->ensureTenantAccess($request, $specification);

        $code = $request->validated('code');
        if ($code !== null && $code !== $specification->code) {
            $exists = Specification::where('tenant_id', $specification->tenant_id)
                ->where('code', $code)
                ->where('id', '!=', $specification->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
            }
        }

        $this->service->update($specification, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Specification $specification): JsonResponse
    {
        $this->ensureTenantAccess($request, $specification);

        $this->service->delete($specification);

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

    private function ensureTenantAccess(Request $request, Specification $specification): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $specification->tenant_id) {
            abort(403);
        }
    }
}

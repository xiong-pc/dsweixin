<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Attribute\StoreAttributeRequest;
use App\Http\Requests\Api\Mall\Attribute\UpdateAttributeRequest;
use App\Http\Resources\Api\Mall\AttributeResource;
use App\Models\Mall\Attribute;
use App\Services\Api\Mall\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttributeController extends Controller
{
    public function __construct(private readonly AttributeService $service) {}

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
            AttributeResource::class
        );
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $exists = Attribute::where('tenant_id', $tenantId)
            ->where('code', $request->validated('code'))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
        }

        $attr = $this->service->create($tenantId, $request->validated());

        return $this->success(new AttributeResource($attr), 'api.created');
    }

    public function show(Request $request, Attribute $attribute): JsonResponse
    {
        $this->ensureTenantAccess($request, $attribute);

        return $this->success(new AttributeResource(
            $attribute->load(['translations', 'values.translations'])
        ));
    }

    /**
     * @throws ValidationException
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute): JsonResponse
    {
        $this->ensureTenantAccess($request, $attribute);

        $code = $request->validated('code');
        if ($code !== null && $code !== $attribute->code) {
            $exists = Attribute::where('tenant_id', $attribute->tenant_id)
                ->where('code', $code)
                ->where('id', '!=', $attribute->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
            }
        }

        $this->service->update($attribute, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Attribute $attribute): JsonResponse
    {
        $this->ensureTenantAccess($request, $attribute);

        $this->service->delete($attribute);

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

    private function ensureTenantAccess(Request $request, Attribute $attribute): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }
        if ($user->isSuperAdmin() === true) {
            return;
        }
        if ((int) $user->tenant_id !== (int) $attribute->tenant_id) {
            abort(403);
        }
    }
}

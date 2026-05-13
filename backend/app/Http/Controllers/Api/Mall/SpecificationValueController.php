<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Specification\StoreSpecificationValueRequest;
use App\Http\Requests\Api\Mall\Specification\UpdateSpecificationValueRequest;
use App\Http\Resources\Api\Mall\SpecificationValueResource;
use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use App\Services\Api\Mall\SpecificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpecificationValueController extends Controller
{
    public function __construct(private readonly SpecificationService $service) {}

    public function index(Request $request, Specification $specification): JsonResponse
    {
        $this->ensureSpecAccess($request, $specification);

        $values = $this->service->listValues($specification);

        return $this->success(
            SpecificationValueResource::collection($values)->toArray($request)
        );
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreSpecificationValueRequest $request, Specification $specification): JsonResponse
    {
        $this->ensureSpecAccess($request, $specification);

        $exists = SpecificationValue::where('specification_id', $specification->id)
            ->where('code', $request->validated('code'))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
        }

        $value = $this->service->createValue($specification, $request->validated());

        return $this->success(new SpecificationValueResource($value), 'api.created');
    }

    public function show(Request $request, SpecificationValue $value): JsonResponse
    {
        $this->ensureValueAccess($request, $value);

        return $this->success(new SpecificationValueResource($value->load('translations')));
    }

    /**
     * @throws ValidationException
     */
    public function update(UpdateSpecificationValueRequest $request, SpecificationValue $value): JsonResponse
    {
        $this->ensureValueAccess($request, $value);

        $code = $request->validated('code');
        if ($code !== null && $code !== $value->code) {
            $exists = SpecificationValue::where('specification_id', $value->specification_id)
                ->where('code', $code)
                ->where('id', '!=', $value->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
            }
        }

        $this->service->updateValue($value, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, SpecificationValue $value): JsonResponse
    {
        $this->ensureValueAccess($request, $value);

        $this->service->deleteValue($value);

        return $this->success(null, 'api.deleted');
    }

    private function ensureSpecAccess(Request $request, Specification $specification): void
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

    private function ensureValueAccess(Request $request, SpecificationValue $value): void
    {
        $value->loadMissing('specification');
        /** @var Specification|null $spec */
        $spec = $value->specification;
        if ($spec === null) {
            abort(404);
        }
        $this->ensureSpecAccess($request, $spec);
    }
}

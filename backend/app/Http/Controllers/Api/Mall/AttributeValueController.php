<?php

namespace App\Http\Controllers\Api\Mall;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Mall\Attribute\StoreAttributeValueRequest;
use App\Http\Requests\Api\Mall\Attribute\UpdateAttributeValueRequest;
use App\Http\Resources\Api\Mall\AttributeValueResource;
use App\Models\Mall\Attribute;
use App\Models\Mall\AttributeValue;
use App\Services\Api\Mall\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttributeValueController extends Controller
{
    public function __construct(private readonly AttributeService $service) {}

    public function index(Request $request, Attribute $attribute): JsonResponse
    {
        $this->ensureAttrAccess($request, $attribute);

        $values = $this->service->listValues($attribute);

        return $this->success(
            AttributeValueResource::collection($values)->toArray($request)
        );
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreAttributeValueRequest $request, Attribute $attribute): JsonResponse
    {
        $this->ensureAttrAccess($request, $attribute);

        $exists = AttributeValue::where('attribute_id', $attribute->id)
            ->where('code', $request->validated('code'))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['code' => __('validation.unique', ['attribute' => 'code'])]);
        }

        $value = $this->service->createValue($attribute, $request->validated());

        return $this->success(new AttributeValueResource($value), 'api.created');
    }

    public function show(Request $request, AttributeValue $value): JsonResponse
    {
        $this->ensureValueAccess($request, $value);

        return $this->success(new AttributeValueResource($value->load('translations')));
    }

    /**
     * @throws ValidationException
     */
    public function update(UpdateAttributeValueRequest $request, AttributeValue $value): JsonResponse
    {
        $this->ensureValueAccess($request, $value);

        $code = $request->validated('code');
        if ($code !== null && $code !== $value->code) {
            $exists = AttributeValue::where('attribute_id', $value->attribute_id)
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

    public function destroy(Request $request, AttributeValue $value): JsonResponse
    {
        $this->ensureValueAccess($request, $value);

        $this->service->deleteValue($value);

        return $this->success(null, 'api.deleted');
    }

    private function ensureAttrAccess(Request $request, Attribute $attribute): void
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

    private function ensureValueAccess(Request $request, AttributeValue $value): void
    {
        $value->loadMissing('attribute');
        /** @var Attribute|null $attr */
        $attr = $value->attribute;
        if ($attr === null) {
            abort(404);
        }
        $this->ensureAttrAccess($request, $attr);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DictItem\StoreDictItemRequest;
use App\Http\Requests\Api\DictItem\UpdateDictItemRequest;
use App\Models\DictItem;
use App\Services\Api\DictItemService;
use Illuminate\Http\JsonResponse;

class DictItemController extends Controller
{
    public function __construct(private readonly DictItemService $service) {}

    public function store(StoreDictItemRequest $request): JsonResponse
    {
        $item = $this->service->create(
            $request->only(['dict_id', 'label', 'value', 'sort', 'status', 'remark'])
        );

        return $this->success($item, 'api.created');
    }

    public function show(DictItem $dictItem): JsonResponse
    {
        return $this->success($dictItem);
    }

    public function update(UpdateDictItemRequest $request, DictItem $dictItem): JsonResponse
    {
        $this->service->update($dictItem, $request->only(['label', 'value', 'sort', 'status', 'remark']));

        return $this->success(null, 'api.updated');
    }

    public function destroy(DictItem $dictItem): JsonResponse
    {
        $this->service->delete($dictItem);

        return $this->success(null, 'api.deleted');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Dict\StoreDictRequest;
use App\Http\Requests\Api\Dict\UpdateDictRequest;
use App\Http\Resources\Api\DictResource;
use App\Models\Dict;
use App\Services\Api\DictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictController extends Controller
{
    public function __construct(private readonly DictService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords']),
                (int) $request->input('pageSize', 10)
            ),
            DictResource::class
        );
    }

    public function store(StoreDictRequest $request): JsonResponse
    {
        $dict = $this->service->create($request->only(['name', 'code', 'status', 'remark']));

        return $this->success(new DictResource($dict), 'api.created');
    }

    public function show(Dict $dict): JsonResponse
    {
        return $this->success(new DictResource($dict->load('items')));
    }

    public function update(UpdateDictRequest $request, Dict $dict): JsonResponse
    {
        $this->service->update($dict, $request->only(['name', 'code', 'status', 'remark']));

        return $this->success(null, 'api.updated');
    }

    public function destroy(Dict $dict): JsonResponse
    {
        $this->service->delete($dict);

        return $this->success(null, 'api.deleted');
    }

    public function items(Dict $dict): JsonResponse
    {
        return $this->success($this->service->items($dict));
    }
}

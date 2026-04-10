<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Config\StoreConfigRequest;
use App\Http\Requests\Api\Config\UpdateConfigRequest;
use App\Http\Resources\Api\ConfigResource;
use App\Models\SysConfig;
use App\Services\Api\ConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(private readonly ConfigService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'name', 'key']),
                (int) $request->input('pageSize', 10),
                (int) $request->input('pageNum', 1)
            ),
            ConfigResource::class
        );
    }

    public function store(StoreConfigRequest $request): JsonResponse
    {
        $config = $this->service->create($request->only(['name', 'key', 'value', 'type', 'remark']));

        return $this->success(new ConfigResource($config), 'api.created');
    }

    public function show(SysConfig $config): JsonResponse
    {
        return $this->success(new ConfigResource($config));
    }

    public function update(UpdateConfigRequest $request, SysConfig $config): JsonResponse
    {
        $this->service->update($config, $request->only(['name', 'key', 'value', 'type', 'remark']));

        return $this->success(null, 'api.updated');
    }

    public function destroy(SysConfig $config): JsonResponse
    {
        $this->service->delete($config);

        return $this->success(null, 'api.deleted');
    }
}

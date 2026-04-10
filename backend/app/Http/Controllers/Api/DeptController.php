<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Dept\StoreDeptRequest;
use App\Http\Requests\Api\Dept\UpdateDeptRequest;
use App\Http\Resources\Api\DeptResource;
use App\Models\Dept;
use App\Services\Api\DeptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeptController extends Controller
{
    public function __construct(private readonly DeptService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tree = $this->service->tree($request->only(['keywords', 'status']));

        return $this->success(array_map(fn($item) => (new DeptResource($item))->resolve(), $tree));
    }

    public function store(StoreDeptRequest $request): JsonResponse
    {
        $dept = $this->service->create($request->only(['parent_id', 'name', 'sort', 'status']));

        return $this->success(new DeptResource($dept), 'api.created');
    }

    public function show(Dept $dept): JsonResponse
    {
        return $this->success(new DeptResource($dept));
    }

    public function update(UpdateDeptRequest $request, Dept $dept): JsonResponse
    {
        $this->service->update($dept, $request->only(['parent_id', 'name', 'sort', 'status']));

        return $this->success(null, 'api.updated');
    }

    public function destroy(Dept $dept): JsonResponse
    {
        $this->service->delete($dept);

        return $this->success(null, 'api.deleted');
    }
}

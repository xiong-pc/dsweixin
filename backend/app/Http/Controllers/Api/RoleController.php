<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Role\StoreRoleRequest;
use App\Http\Requests\Api\Role\UpdateRoleRequest;
use App\Http\Resources\Api\RoleResource;
use App\Models\Role;
use App\Services\Api\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list($request->only(['keywords']), (int) $request->input('pageSize', 10)),
            RoleResource::class
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->service->create(
            $request->only(['name', 'code', 'data_scope', 'sort', 'status', 'remark']),
            $request->input('menuIds', [])
        );

        return $this->success(new RoleResource($role->load('menus')), 'api.created');
    }

    public function show(Role $role): JsonResponse
    {
        return $this->success(new RoleResource($role->load('menus')));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->service->update(
            $role,
            $request->only(['name', 'code', 'data_scope', 'sort', 'status', 'remark']),
            $request->has('menuIds') ? $request->input('menuIds') : null
        );

        return $this->success(null, 'api.updated');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->service->delete($role);

        return $this->success(null, 'api.deleted');
    }

    public function menus(Role $role): JsonResponse
    {
        return $this->success($this->service->getMenuIds($role));
    }

    public function updateMenus(Request $request, Role $role): JsonResponse
    {
        $this->service->updateMenus($role, $request->input('menuIds', []));

        return $this->success(null, 'api.menu_assigned');
    }
}

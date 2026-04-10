<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Menu\StoreMenuRequest;
use App\Http\Requests\Api\Menu\UpdateMenuRequest;
use App\Http\Resources\Api\MenuResource;
use App\Models\Menu;
use App\Services\Api\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(private readonly MenuService $service) {}

    public function index(Request $request): JsonResponse
    {
        $tree = $this->service->tree($request->only(['keywords']));

        return $this->success(array_map(fn($item) => (new MenuResource($item))->resolve(), $tree));
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $menu = $this->service->create($request->only([
            'parent_id', 'name', 'type', 'path', 'component',
            'permission', 'icon', 'sort', 'visible', 'redirect',
        ]));

        return $this->success(new MenuResource($menu), 'api.created');
    }

    public function show(Menu $menu): JsonResponse
    {
        return $this->success(new MenuResource($menu));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): JsonResponse
    {
        $this->service->update($menu, $request->only([
            'parent_id', 'name', 'type', 'path', 'component',
            'permission', 'icon', 'sort', 'visible', 'redirect',
        ]));

        return $this->success(null, 'api.updated');
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $this->service->delete($menu);

        return $this->success(null, 'api.deleted');
    }
}

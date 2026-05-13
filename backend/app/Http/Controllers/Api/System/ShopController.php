<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Shop\StoreShopRequest;
use App\Http\Requests\Api\Shop\UpdateShopRequest;
use App\Http\Resources\Api\ShopResource;
use App\Models\Shop;
use App\Services\Api\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(private readonly ShopService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->user(),
                $request->only(['keywords', 'name', 'status', 'tenant_id']),
                (int) $request->input('pageSize', 10),
                (int) $request->input('pageNum', 1)
            ),
            ShopResource::class
        );
    }

    public function store(StoreShopRequest $request): JsonResponse
    {
        $data = $request->only([
            'tenant_id', 'name', 'code', 'subdomain', 'locale', 'currency',
            'timezone', 'theme_id', 'status', 'sort', 'remark',
        ]);

        if (! $request->user()->isSuperAdmin()) {
            $data['tenant_id'] = $request->user()->tenant_id;
        } elseif (empty($data['tenant_id'])) {
            $data['tenant_id'] = $request->user()->tenant_id;
        }

        $shop = $this->service->create($data);

        return $this->success(new ShopResource($shop), 'api.created');
    }

    public function show(Request $request, Shop $shop): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()
            && (int) $shop->tenant_id !== (int) $request->user()->tenant_id) {
            return $this->error('api.forbidden', 403);
        }

        return $this->success(new ShopResource($shop));
    }

    public function update(UpdateShopRequest $request, Shop $shop): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()
            && (int) $shop->tenant_id !== (int) $request->user()->tenant_id) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->update(
            $shop,
            $request->only([
                'name', 'code', 'subdomain', 'locale', 'currency',
                'timezone', 'theme_id', 'status', 'sort', 'remark',
            ])
        );

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Shop $shop): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()
            && (int) $shop->tenant_id !== (int) $request->user()->tenant_id) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($shop);

        return $this->success(null, 'api.deleted');
    }
}

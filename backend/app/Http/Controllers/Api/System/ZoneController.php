<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Zone\StoreZoneRequest;
use App\Http\Requests\Api\Zone\UpdateZoneRequest;
use App\Http\Resources\Api\ZoneResource;
use App\Models\Zone;
use App\Services\Api\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function __construct(private readonly ZoneService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'is_active']),
                (int) $request->input('pageSize', 20),
                (int) $request->input('pageNum', 1)
            ),
            ZoneResource::class
        );
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = $this->service->create($request->validated());

        return $this->success(new ZoneResource($zone), 'api.created');
    }

    public function show(Zone $zone): JsonResponse
    {
        return $this->success(new ZoneResource($zone->load('countries:id,code,name')));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $this->service->update($zone, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Zone $zone): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($zone);

        return $this->success(null, 'api.deleted');
    }
}

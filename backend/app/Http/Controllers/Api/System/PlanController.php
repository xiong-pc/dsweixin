<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Plan\StorePlanRequest;
use App\Http\Requests\Api\Plan\UpdatePlanRequest;
use App\Http\Resources\Api\PlanResource;
use App\Models\Plan;
use App\Services\Api\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'status']),
                (int) $request->input('pageSize', 10),
                (int) $request->input('pageNum', 1)
            ),
            PlanResource::class
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->service->create($request->validated());

        return $this->success(new PlanResource($plan), 'api.created');
    }

    public function show(Plan $plan): JsonResponse
    {
        return $this->success(new PlanResource($plan));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $this->service->update($plan, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        if (! $this->service->delete($plan)) {
            return $this->error('api.plan_in_use', 409);
        }

        return $this->success(null, 'api.deleted');
    }
}

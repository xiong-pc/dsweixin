<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Country\StoreCountryRequest;
use App\Http\Requests\Api\Country\UpdateCountryRequest;
use App\Http\Resources\Api\CountryResource;
use App\Models\Country;
use App\Services\Api\CountryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function __construct(private readonly CountryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'continent', 'is_active']),
                (int) $request->input('pageSize', 50),
                (int) $request->input('pageNum', 1)
            ),
            CountryResource::class
        );
    }

    public function store(StoreCountryRequest $request): JsonResponse
    {
        $country = $this->service->create($request->validated());

        return $this->success(new CountryResource($country), 'api.created');
    }

    public function show(Country $country): JsonResponse
    {
        return $this->success(new CountryResource($country->load('translations')));
    }

    public function update(UpdateCountryRequest $request, Country $country): JsonResponse
    {
        $this->service->update($country, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Country $country): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($country);

        return $this->success(null, 'api.deleted');
    }
}

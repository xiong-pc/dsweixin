<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Currency\StoreCurrencyRequest;
use App\Http\Requests\Api\Currency\UpdateCurrencyRequest;
use App\Http\Resources\Api\CurrencyResource;
use App\Models\Currency;
use App\Services\Api\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'is_active']),
                (int) $request->input('pageSize', 50),
                (int) $request->input('pageNum', 1)
            ),
            CurrencyResource::class
        );
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = $this->service->create($request->validated());

        return $this->success(new CurrencyResource($currency), 'api.created');
    }

    public function show(Currency $currency): JsonResponse
    {
        return $this->success(new CurrencyResource($currency));
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): JsonResponse
    {
        $this->service->update($currency, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Currency $currency): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($currency);

        return $this->success(null, 'api.deleted');
    }
}

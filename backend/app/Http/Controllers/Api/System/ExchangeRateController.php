<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\ExchangeRate\StoreExchangeRateRequest;
use App\Http\Requests\Api\ExchangeRate\UpdateExchangeRateRequest;
use App\Http\Resources\Api\ExchangeRateResource;
use App\Jobs\SyncExchangeRatesJob;
use App\Models\ExchangeRate;
use App\Services\Api\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['from_currency', 'to_currency', 'source']),
                (int) $request->input('pageSize', 20),
                (int) $request->input('pageNum', 1)
            ),
            ExchangeRateResource::class
        );
    }

    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $rate = $this->service->upsert($request->validated());

        return $this->success(new ExchangeRateResource($rate), 'api.created');
    }

    public function show(ExchangeRate $exchangeRate): JsonResponse
    {
        return $this->success(new ExchangeRateResource($exchangeRate));
    }

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->update($request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($exchangeRate);

        return $this->success(null, 'api.deleted');
    }

    public function sync(Request $request): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $base = strtoupper((string) $request->input('base_currency', 'CNY'));
        $rates = (array) $request->input('rates', []);
        $source = (string) $request->input('source', 'manual');

        if (empty($rates)) {
            return $this->error('api.no_rates_supplied', 422);
        }

        // 同步派发，便于测试与即时反馈；后续可改 dispatch() 异步
        (new SyncExchangeRatesJob($base, $rates, $source))->handle($this->service);

        return $this->success(['synced' => count($rates)], 'api.sync_dispatched');
    }
}

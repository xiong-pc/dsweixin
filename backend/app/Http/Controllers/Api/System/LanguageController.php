<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Language\StoreLanguageRequest;
use App\Http\Requests\Api\Language\UpdateLanguageRequest;
use App\Http\Resources\Api\LanguageResource;
use App\Models\Language;
use App\Services\Api\LanguageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function __construct(private readonly LanguageService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'is_active']),
                (int) $request->input('pageSize', 50),
                (int) $request->input('pageNum', 1)
            ),
            LanguageResource::class
        );
    }

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $language = $this->service->create($request->validated());

        return $this->success(new LanguageResource($language), 'api.created');
    }

    public function show(Language $language): JsonResponse
    {
        return $this->success(new LanguageResource($language));
    }

    public function update(UpdateLanguageRequest $request, Language $language): JsonResponse
    {
        $this->service->update($language, $request->validated());

        return $this->success(null, 'api.updated');
    }

    public function destroy(Request $request, Language $language): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->error('api.forbidden', 403);
        }

        $this->service->delete($language);

        return $this->success(null, 'api.deleted');
    }
}

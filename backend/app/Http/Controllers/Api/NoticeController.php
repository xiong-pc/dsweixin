<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Notice\StoreNoticeRequest;
use App\Http\Requests\Api\Notice\UpdateNoticeRequest;
use App\Http\Resources\Api\NoticeResource;
use App\Models\Notice;
use App\Services\Api\NoticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoticeController extends Controller
{
    public function __construct(private readonly NoticeService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginate(
            $this->service->list(
                $request->only(['keywords', 'title', 'type', 'status']),
                (int) $request->input('pageSize', 10),
                (int) $request->input('pageNum', 1)
            ),
            NoticeResource::class
        );
    }

    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $notice = $this->service->create(
            $request->only(['title', 'type', 'level', 'content', 'status']),
            auth()->id()
        );

        return $this->success(new NoticeResource($notice), 'api.created');
    }

    public function show(Notice $notice): JsonResponse
    {
        return $this->success(new NoticeResource($notice->load('publisher')));
    }

    public function update(UpdateNoticeRequest $request, Notice $notice): JsonResponse
    {
        $this->service->update($notice, $request->only(['title', 'type', 'level', 'content']));

        return $this->success(null, 'api.updated');
    }

    public function destroy(Notice $notice): JsonResponse
    {
        $this->service->delete($notice);

        return $this->success(null, 'api.deleted');
    }

    public function publish(Notice $notice): JsonResponse
    {
        $this->service->publish($notice);

        return $this->success(null, 'api.notice_published');
    }

    public function revoke(Notice $notice): JsonResponse
    {
        $this->service->revoke($notice);

        return $this->success(null, 'api.notice_revoked');
    }
}

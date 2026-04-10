<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\User\StoreUserRequest;
use App\Http\Requests\Api\User\UpdateStatusRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\Api\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list(
            $request->only(['keywords', 'status', 'dept_id']),
            (int) $request->input('pageSize', 10)
        );

        return $this->paginate($paginator, UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create(
            $request->only(['username', 'nickname', 'password', 'email', 'phone', 'avatar', 'gender', 'status', 'dept_id']),
            $request->input('roleIds', [])
        );

        return $this->success(new UserResource($user), 'api.created');
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['dept', 'roles']);

        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->service->update(
            $user,
            $request->only(['username', 'nickname', 'email', 'phone', 'avatar', 'gender', 'status', 'dept_id']),
            $request->has('roleIds') ? $request->input('roleIds') : null
        );

        return $this->success(null, 'api.updated');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->service->delete($user);

        return $this->success(null, 'api.deleted');
    }

    public function updateStatus(UpdateStatusRequest $request, User $user): JsonResponse
    {
        $this->service->updateStatus($user, (int) $request->status);

        return $this->success(null, 'api.status_updated');
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->service->resetPassword($user);

        return $this->success(null, 'api.password_reset');
    }
}

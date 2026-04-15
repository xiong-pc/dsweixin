<?php

/**
 * @Author: xiong-pc
 *
 * @Email: 562740366@qq.com
 *
 * @Date: 2026-04-15 00:00:00
 *
 * @Version: 1.0.0
 */

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UserLog\StoreUserLogRequest;
use App\Http\Requests\Api\UserLog\UpdateUserLogRequest;
use App\Services\Api\UserLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLogController extends Controller
{
    public function __construct(private readonly UserLogService $service) {}

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 获取用户操作日志列表（分页）
     *
     * @param  Request  $request  请求对象
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginate($this->service->list($request->all()));
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 创建用户操作日志
     *
     * @param  StoreUserLogRequest  $request  请求对象
     */
    public function store(StoreUserLogRequest $request): JsonResponse
    {
        $userLog = $this->service->store($request->validated());

        return $this->success($userLog, 'api.created');
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 获取用户操作日志详情
     *
     * @param  int  $id  日志 ID
     */
    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->show($id));
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 更新用户操作日志
     *
     * @param  UpdateUserLogRequest  $request  请求对象
     * @param  int  $id  日志 ID
     */
    public function update(UpdateUserLogRequest $request, int $id): JsonResponse
    {
        $this->service->update($id, $request->validated());

        return $this->success(null, 'api.updated');
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 删除用户操作日志
     *
     * @param  int  $id  日志 ID
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return $this->success(null, 'api.deleted');
    }
}

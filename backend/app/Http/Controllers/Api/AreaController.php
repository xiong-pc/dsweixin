<?php

/**
 * @Author: xiong-pc
 *
 * @Email: 562740366@qq.com
 *
 * @Date: 2026-04-10 12:00:00
 *
 * @Version: 1.0.0
 */

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Area\StoreAreaRequest;
use App\Http\Requests\Api\Area\UpdateAreaRequest;
use App\Services\Api\AreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function __construct(private readonly AreaService $service) {}

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 获取区域列表（分页）
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
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 创建区域
     *
     * @param  StoreAreaRequest  $request  请求对象
     */
    public function store(StoreAreaRequest $request): JsonResponse
    {
        $area = $this->service->store($request->validated());

        return $this->success($area, 'api.created');
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 获取区域详情
     *
     * @param  int  $id  区域 ID
     */
    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->show($id));
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 更新区域
     *
     * @param  UpdateAreaRequest  $request  请求对象
     * @param  int  $id  区域 ID
     */
    public function update(UpdateAreaRequest $request, int $id): JsonResponse
    {
        $this->service->update($id, $request->validated());

        return $this->success(null, 'api.updated');
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 删除区域
     *
     * @param  int  $id  区域 ID
     */
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return $this->success(null, 'api.deleted');
    }
}

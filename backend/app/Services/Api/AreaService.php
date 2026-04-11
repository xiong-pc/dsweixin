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

namespace App\Services\Api;

use App\Models\Area;
use Illuminate\Pagination\LengthAwarePaginator;

class AreaService
{
    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 获取地区列表（分页，支持按名称/简称/级别/父级/状态筛选）
     *
     * @param  array  $params  过滤参数（keywords, level, pid, status, pageSize, pageNum）
     */
    public function list(array $params): LengthAwarePaginator
    {
        $query = Area::query()->orderBy('sort')->orderBy('id');

        if (! empty($params['keywords'])) {
            $kw = $params['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('shortname', 'like', "%{$kw}%");
            });
        }

        if (isset($params['level']) && $params['level'] !== '') {
            $query->where('level', $params['level']);
        }

        if (isset($params['pid']) && $params['pid'] !== '') {
            $query->where('pid', $params['pid']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        $pageSize = $params['pageSize'] ?? $params['page_size'] ?? 15;
        $page = $params['pageNum'] ?? $params['page'] ?? 1;

        return $query->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 创建地区
     *
     * @param  array  $data  创建数据
     */
    public function store(array $data): Area
    {
        return Area::create($data);
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 获取地区详情
     *
     * @param  int  $id  地区 ID
     */
    public function show(int $id): Area
    {
        return Area::findOrFail($id);
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 更新地区
     *
     * @param  int  $id  地区 ID
     * @param  array  $data  更新数据
     */
    public function update(int $id, array $data): void
    {
        Area::findOrFail($id)->update($data);
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-10 12:00:00
     *
     * @Description: 删除地区（有子级时拒绝删除）
     *
     * @param  int  $id  地区 ID
     */
    public function destroy(int $id): void
    {
        $area = Area::findOrFail($id);

        if (Area::where('pid', $id)->exists()) {
            abort(400, '该地区下存在子级，无法删除');
        }

        $area->delete();
    }
}

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

namespace App\Services\Api;

use App\Models\UserLog;
use Illuminate\Pagination\LengthAwarePaginator;

class UserLogService
{
    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 获取用户操作日志列表（分页，支持按用户/站点/操作行为筛选）
     *
     * @param  array  $params  过滤参数（keywords, uid, site_id, action_name, pageSize, pageNum）
     */
    public function list(array $params): LengthAwarePaginator
    {
        $query = UserLog::query()->orderByDesc('id');

        if (! empty($params['keywords'])) {
            $kw = $params['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('username', 'like', "%{$kw}%")
                    ->orWhere('action_name', 'like', "%{$kw}%")
                    ->orWhere('ip', 'like', "%{$kw}%");
            });
        }

        if (isset($params['uid']) && $params['uid'] !== '') {
            $query->where('uid', $params['uid']);
        }

        if (isset($params['site_id']) && $params['site_id'] !== '') {
            $query->where('site_id', $params['site_id']);
        }

        if (isset($params['action_name']) && $params['action_name'] !== '') {
            $query->where('action_name', $params['action_name']);
        }

        $pageSize = $params['pageSize'] ?? $params['page_size'] ?? 15;
        $page = $params['pageNum'] ?? $params['page'] ?? 1;

        return $query->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 创建用户操作日志
     *
     * @param  array  $data  创建数据
     */
    public function store(array $data): UserLog
    {
        return UserLog::create($data);
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
    public function show(int $id): UserLog
    {
        return UserLog::findOrFail($id);
    }

    /**
     * @Author: xiong-pc
     *
     * @Date: 2026-04-15 00:00:00
     *
     * @Description: 更新用户操作日志
     *
     * @param  int  $id  日志 ID
     * @param  array  $data  更新数据
     */
    public function update(int $id, array $data): void
    {
        UserLog::findOrFail($id)->update($data);
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
    public function destroy(int $id): void
    {
        UserLog::findOrFail($id)->delete();
    }
}

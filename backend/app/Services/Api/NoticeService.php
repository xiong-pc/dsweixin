<?php

namespace App\Services\Api;

use App\Models\Notice;
use Illuminate\Pagination\LengthAwarePaginator;

class NoticeService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Notice::with('publisher');

        if (!empty($filters['keywords'])) {
            $query->where('title', 'like', '%' . $filters['keywords'] . '%');
        }

        if (!empty($filters['title'])) {
            $query->where('title', 'like', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data, int $publisherId): Notice
    {
        $data['publisher_id'] = $publisherId;

        if (($data['status'] ?? 0) == 1) {
            $data['publish_time'] = now();
        }

        return Notice::create($data);
    }

    public function update(Notice $notice, array $data): void
    {
        $notice->update($data);
    }

    public function delete(Notice $notice): void
    {
        $notice->delete();
    }

    public function publish(Notice $notice): void
    {
        $notice->update(['status' => 1, 'publish_time' => now()]);
    }

    public function revoke(Notice $notice): void
    {
        $notice->update(['status' => 2]);
    }
}

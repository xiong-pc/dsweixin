<?php

namespace App\Services\Api;

use App\Models\SysConfig;
use Illuminate\Pagination\LengthAwarePaginator;

class ConfigService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = SysConfig::query();

        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('key', 'like', "%{$kw}%");
            });
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (!empty($filters['key'])) {
            $query->where('key', 'like', '%'.$filters['key'].'%');
        }

        return $query->orderBy('id', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): SysConfig
    {
        return SysConfig::create($data);
    }

    public function update(SysConfig $config, array $data): void
    {
        $config->update($data);
    }

    public function delete(SysConfig $config): void
    {
        $config->delete();
    }
}

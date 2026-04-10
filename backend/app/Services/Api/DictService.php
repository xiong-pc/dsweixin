<?php

namespace App\Services\Api;

use App\Models\Dict;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DictService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = Dict::query();

        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        return $query->orderBy('id', 'desc')->paginate($pageSize);
    }

    public function create(array $data): Dict
    {
        return Dict::create($data);
    }

    public function update(Dict $dict, array $data): void
    {
        $dict->update($data);
    }

    public function delete(Dict $dict): void
    {
        $dict->items()->delete();
        $dict->delete();
    }

    public function items(Dict $dict): Collection
    {
        return $dict->items;
    }
}

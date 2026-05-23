<?php

namespace App\Services\Api;

use App\Models\Language;
use Illuminate\Pagination\LengthAwarePaginator;

class LanguageService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Language::query();

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%")
                    ->orWhere('native_name', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): Language
    {
        return Language::create($data);
    }

    public function update(Language $language, array $data): void
    {
        $language->update($data);
    }

    public function delete(Language $language): void
    {
        $language->delete();
    }
}

<?php

namespace App\Services\Api\Mall;

use App\Models\Mall\Specification;
use App\Models\Mall\SpecificationValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SpecificationService
{
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Specification::query()->with(['translations', 'values.translations']);

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhereHas('translations', fn ($qt) => $qt->where('name', 'like', "%{$kw}%"));
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(int $tenantId, array $data): Specification
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['tenant_id'] = $tenantId;
            $spec = Specification::create($data);

            if (! empty($translations)) {
                $spec->setTranslations($translations);
            }

            return $spec->load(['translations', 'values.translations']);
        });
    }

    public function update(Specification $spec, array $data): void
    {
        DB::transaction(function () use ($spec, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['tenant_id']); // tenant_id 不允许变更

            $spec->update($data);

            if (is_array($translations)) {
                $spec->syncTranslations($translations);
            }
        });
    }

    public function delete(Specification $spec): void
    {
        DB::transaction(function () use ($spec) {
            // 级联：删值翻译 → 值 → 规格翻译 → 规格
            /** @var SpecificationValue $value */
            foreach ($spec->values()->get() as $value) {
                $value->translations()->delete();
                $value->delete();
            }
            $spec->translations()->delete();
            $spec->delete();
        });
    }

    public function createValue(Specification $spec, array $data): SpecificationValue
    {
        return DB::transaction(function () use ($spec, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['specification_id'] = $spec->id;
            $value = SpecificationValue::create($data);

            if (! empty($translations)) {
                $value->setTranslations($translations);
            }

            return $value->load('translations');
        });
    }

    public function updateValue(SpecificationValue $value, array $data): void
    {
        DB::transaction(function () use ($value, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['specification_id']);

            $value->update($data);

            if (is_array($translations)) {
                $value->syncTranslations($translations);
            }
        });
    }

    public function deleteValue(SpecificationValue $value): void
    {
        DB::transaction(function () use ($value) {
            $value->translations()->delete();
            $value->delete();
        });
    }

    public function listValues(Specification $spec): Collection
    {
        return $spec->values()->with('translations')->orderBy('sort')->orderBy('id')->get();
    }
}

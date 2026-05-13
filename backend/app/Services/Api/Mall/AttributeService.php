<?php

namespace App\Services\Api\Mall;

use App\Models\Mall\Attribute;
use App\Models\Mall\AttributeValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AttributeService
{
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = Attribute::query()->with(['translations', 'values.translations']);

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

    public function create(int $tenantId, array $data): Attribute
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['tenant_id'] = $tenantId;
            $attr = Attribute::create($data);

            if (! empty($translations)) {
                $attr->setTranslations($translations);
            }

            return $attr->load(['translations', 'values.translations']);
        });
    }

    public function update(Attribute $attr, array $data): void
    {
        DB::transaction(function () use ($attr, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['tenant_id']);

            $attr->update($data);

            if (is_array($translations)) {
                $attr->syncTranslations($translations);
            }
        });
    }

    public function delete(Attribute $attr): void
    {
        DB::transaction(function () use ($attr) {
            /** @var AttributeValue $value */
            foreach ($attr->values()->get() as $value) {
                $value->translations()->delete();
                $value->delete();
            }
            $attr->translations()->delete();
            $attr->delete();
        });
    }

    public function createValue(Attribute $attr, array $data): AttributeValue
    {
        return DB::transaction(function () use ($attr, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['attribute_id'] = $attr->id;
            $value = AttributeValue::create($data);

            if (! empty($translations)) {
                $value->setTranslations($translations);
            }

            return $value->load('translations');
        });
    }

    public function updateValue(AttributeValue $value, array $data): void
    {
        DB::transaction(function () use ($value, $data) {
            $translations = $data['translations'] ?? null;
            unset($data['translations'], $data['attribute_id']);

            $value->update($data);

            if (is_array($translations)) {
                $value->syncTranslations($translations);
            }
        });
    }

    public function deleteValue(AttributeValue $value): void
    {
        DB::transaction(function () use ($value) {
            $value->translations()->delete();
            $value->delete();
        });
    }

    public function listValues(Attribute $attr): Collection
    {
        return $attr->values()->with('translations')->orderBy('sort')->orderBy('id')->get();
    }
}

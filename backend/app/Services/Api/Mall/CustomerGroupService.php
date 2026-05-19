<?php

namespace App\Services\Api\Mall;

use App\Exceptions\BusinessException;
use App\Models\Mall\Customer;
use App\Models\Mall\CustomerGroup;
use App\Models\Mall\CustomerGroupTranslation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerGroupService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $pageSize = 20, int $page = 1): LengthAwarePaginator
    {
        $query = CustomerGroup::query()->with('translations');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['keywords'])) {
            $kw = (string) $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', "%{$kw}%")
                    ->orWhereHas('translations', fn ($qt) => $qt->where('name', 'like', "%{$kw}%"));
            });
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $tenantId, array $data): CustomerGroup
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $translations = is_array($data['translations'] ?? null) ? $data['translations'] : [];
            unset($data['translations']);

            $data['tenant_id'] = $tenantId;
            $group = CustomerGroup::create($data);

            $this->saveTranslations($group, $translations);

            return $group->load('translations');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerGroup $group, array $data): void
    {
        DB::transaction(function () use ($group, $data) {
            $translations = array_key_exists('translations', $data) && is_array($data['translations'])
                ? $data['translations'] : null;
            unset($data['translations'], $data['tenant_id']);

            $group->update($data);

            if ($translations !== null) {
                $group->translations()->delete();
                $this->saveTranslations($group->fresh() ?? $group, $translations);
            }
        });
    }

    public function delete(CustomerGroup $group): void
    {
        if (Customer::query()->where('group_id', $group->id)->exists()) {
            throw new BusinessException('api.customer_group_has_customers');
        }

        DB::transaction(function () use ($group) {
            $group->translations()->delete();
            $group->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    private function saveTranslations(CustomerGroup $group, array $translations): void
    {
        foreach ($translations as $t) {
            $locale = $t['locale'] ?? null;
            $name = $t['name'] ?? null;
            if (! is_string($locale) || $locale === '' || ! is_string($name) || $name === '') {
                continue;
            }
            $description = $t['description'] ?? '';
            if (! is_string($description)) {
                $description = '';
            }

            CustomerGroupTranslation::updateOrCreate(
                ['customer_group_id' => $group->id, 'locale' => $locale],
                ['name' => $name, 'description' => $description]
            );
        }
    }
}

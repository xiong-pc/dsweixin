<?php

namespace App\Services\Api;

use App\Models\Plan;
use Illuminate\Pagination\LengthAwarePaginator;

class PlanService
{
    public function list(array $filters, int $pageSize = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Plan::query();

        if (! empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                    ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('sort')->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): void
    {
        $plan->update($data);
    }

    public function delete(Plan $plan): bool
    {
        if ($plan->tenants()->exists()) {
            return false;
        }

        $plan->delete();

        return true;
    }
}

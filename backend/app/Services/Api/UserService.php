<?php

namespace App\Services\Api;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function list(array $filters, int $pageSize = 10): LengthAwarePaginator
    {
        $query = User::with(['dept', 'roles']);

        if (!empty($filters['keywords'])) {
            $kw = $filters['keywords'];
            $query->where(function ($q) use ($kw) {
                $q->where('username', 'like', "%{$kw}%")
                  ->orWhere('nickname', 'like', "%{$kw}%")
                  ->orWhere('phone', 'like', "%{$kw}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['dept_id'])) {
            $query->where('dept_id', $filters['dept_id']);
        }

        return $query->orderBy('id', 'desc')->paginate($pageSize);
    }

    public function create(array $data, array $roleIds = []): User
    {
        $data['name'] = $data['nickname'];
        $user = User::create($data);

        if (!empty($roleIds)) {
            $user->roles()->sync($roleIds);
        }

        return $user;
    }

    public function update(User $user, array $data, ?array $roleIds = null): void
    {
        if (isset($data['nickname'])) {
            $data['name'] = $data['nickname'];
        }

        $user->update($data);

        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function updateStatus(User $user, int $status): void
    {
        $user->update(['status' => $status]);
    }

    public function resetPassword(User $user, string $password = '123456'): void
    {
        $user->update(['password' => Hash::make($password)]);
    }
}

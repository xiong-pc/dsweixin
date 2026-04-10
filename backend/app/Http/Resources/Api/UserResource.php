<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'username'   => $this->username,
            'nickname'   => $this->nickname,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'avatar'     => $this->avatar,
            'gender'     => $this->gender,
            'status'     => $this->status,
            'dept_id'    => $this->dept_id,
            'tenant_id'  => $this->tenant_id,
            'createdAt'  => $this->created_at?->toDateTimeString(),
            // 关联（whenLoaded 只在预加载时输出，避免 N+1）
            'dept'       => $this->whenLoaded('dept', fn() => [
                'id'   => $this->dept->id,
                'name' => $this->dept->name,
            ]),
            'roles'      => $this->whenLoaded('roles', fn() =>
                $this->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'code' => $r->code])
            ),
        ];
        // 注意：password / remember_token 不在此处输出
    }
}

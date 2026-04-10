<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'code'       => $this->code,
            'dataScope'  => $this->data_scope,
            'sort'       => $this->sort,
            'status'     => $this->status,
            'remark'     => $this->remark,
            'createdAt'  => $this->created_at?->toDateTimeString(),
            'menuIds'    => $this->whenLoaded('menus', fn() => $this->menus->pluck('id')),
        ];
    }
}

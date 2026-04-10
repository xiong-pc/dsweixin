<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $rawChildren = data_get($r, 'children', []);
        $children = is_array($rawChildren) && $rawChildren !== []
            ? array_map(fn ($child) => (new static($child))->resolve(), $rawChildren)
            : null;

        $parentId = data_get($r, 'parent_id');

        return [
            'id'         => data_get($r, 'id'),
            'parentId'   => $parentId,
            'parent_id'  => $parentId,
            'name'       => data_get($r, 'name'),
            'sort'       => data_get($r, 'sort'),
            'status'     => data_get($r, 'status'),
            'leader'     => data_get($r, 'leader'),
            'phone'      => data_get($r, 'phone'),
            'email'      => data_get($r, 'email'),
            'created_at' => data_get($r, 'created_at'),
            'children'   => $this->when($children !== null, $children ?? []),
        ];
    }
}

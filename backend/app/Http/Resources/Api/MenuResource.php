<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $rawChildren = data_get($r, 'children', []);
        $children = is_array($rawChildren) && $rawChildren !== []
            ? array_map(fn ($child) => (new static($child))->resolve(), $rawChildren)
            : null;

        return [
            'id'         => data_get($r, 'id'),
            'parentId'   => data_get($r, 'parent_id'),
            'name'       => data_get($r, 'name'),
            'type'       => data_get($r, 'type'),
            'path'       => data_get($r, 'path'),
            'component'  => data_get($r, 'component'),
            'permission' => data_get($r, 'permission'),
            'icon'       => data_get($r, 'icon'),
            'sort'       => data_get($r, 'sort'),
            'visible'    => (bool) data_get($r, 'visible'),
            'redirect'   => data_get($r, 'redirect'),
            'status'     => (int) data_get($r, 'visible', 1),
            'children'   => $this->when($children !== null, $children ?? []),
        ];
    }
}

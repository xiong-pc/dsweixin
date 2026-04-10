<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'code'      => $this->code,
            'status'    => $this->status,
            'remark'    => $this->remark,
            'createdAt' => $this->created_at?->toDateTimeString(),
            'items'     => $this->whenLoaded('items', fn() =>
                $this->items->map(fn($item) => [
                    'id'     => $item->id,
                    'label'  => $item->label,
                    'value'  => $item->value,
                    'sort'   => $item->sort,
                    'status' => $item->status,
                    'remark' => $item->remark,
                ])
            ),
        ];
    }
}

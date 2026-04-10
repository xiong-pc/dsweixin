<?php

namespace App\Services\Api;

use App\Models\DictItem;

class DictItemService
{
    public function create(array $data): DictItem
    {
        return DictItem::create($data);
    }

    public function update(DictItem $dictItem, array $data): void
    {
        $dictItem->update($data);
    }

    public function delete(DictItem $dictItem): void
    {
        $dictItem->delete();
    }
}

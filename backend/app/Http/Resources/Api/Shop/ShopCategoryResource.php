<?php

namespace App\Http\Resources\Api\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商城前台类目（公开）资源（M11-PR43）。
 *
 * 按 X-Locale header 解析当前语言的 name / description；缺省回退首条翻译。
 */
class ShopCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $locale = (string) ($request->header('X-Locale') ?? '');
        $translation = $this->resolveTranslation(data_get($r, 'translations'), $locale);

        return [
            'id' => data_get($r, 'id'),
            'parent_id' => data_get($r, 'parent_id'),
            'code' => data_get($r, 'code'),
            'cover_image' => data_get($r, 'cover_image'),
            'sort' => data_get($r, 'sort'),
            'name' => data_get($translation, 'name', ''),
            'description' => data_get($translation, 'description', ''),
        ];
    }

    /**
     * @param  iterable<mixed>|null  $translations
     */
    private function resolveTranslation(mixed $translations, string $locale): mixed
    {
        if (! is_iterable($translations)) {
            return null;
        }

        $first = null;
        foreach ($translations as $t) {
            if ($first === null) {
                $first = $t;
            }
            if ($locale !== '' && (string) data_get($t, 'locale') === $locale) {
                return $t;
            }
        }

        return $first;
    }
}

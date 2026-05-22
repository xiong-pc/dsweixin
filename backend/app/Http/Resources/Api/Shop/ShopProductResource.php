<?php

namespace App\Http\Resources\Api\Shop;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 商城前台商品（公开）资源（M11-PR43）。
 *
 * 与 Mall\ProductResource 区别：
 *   - 不暴露 sold_count / view_count / sort 等运营字段
 *   - 按 X-Locale header 命中翻译，把当前语言的 name / slug / short_description 提到顶层
 *   - 退化策略：未命中目标语言时回退第一条 translation，避免空白
 */
class ShopProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;
        $locale = (string) ($request->header('X-Locale') ?? '');
        $translation = $this->resolveTranslation(data_get($r, 'translations'), $locale);

        return [
            'id' => data_get($r, 'id'),
            'category_id' => data_get($r, 'category_id'),
            'brand_id' => data_get($r, 'brand_id'),
            'cover_image' => data_get($r, 'cover_image'),
            'images' => data_get($r, 'images') ?? [],
            'base_price' => data_get($r, 'base_price'),
            'base_currency' => data_get($r, 'base_currency'),
            'name' => data_get($translation, 'name', ''),
            'slug' => data_get($translation, 'slug', ''),
            'short_description' => data_get($translation, 'short_description', ''),
            'description' => data_get($translation, 'description', ''),
            'seo' => [
                'title' => data_get($translation, 'seo_title', ''),
                'keywords' => data_get($translation, 'seo_keywords', ''),
                'description' => data_get($translation, 'seo_description', ''),
            ],
            'translations' => $this->formatTranslations(data_get($r, 'translations')),
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

    /**
     * 提供完整 translations 列表，方便前端构建 hreflang 切换器（PR47）。
     *
     * @param  iterable<mixed>|null  $translations
     * @return array<int, array<string, mixed>>
     */
    private function formatTranslations(mixed $translations): array
    {
        if (! is_iterable($translations)) {
            return [];
        }

        $result = [];
        foreach ($translations as $t) {
            $result[] = [
                'locale' => data_get($t, 'locale'),
                'name' => data_get($t, 'name'),
                'slug' => data_get($t, 'slug'),
            ];
        }

        return $result;
    }
}

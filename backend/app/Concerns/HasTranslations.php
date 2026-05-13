<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 通用翻译关系 trait。
 *
 * 默认约定：
 *   - 主模型 App\Models\Foo\Bar 的翻译模型为 App\Models\Foo\BarTranslation
 *   - 翻译表外键为 bar_id（蛇形单数）
 *
 * 不符合约定时，在模型里 override translationModel() / translationForeignKey()。
 *
 * @mixin Model
 *
 * @property-read Collection<int, Model> $translations
 */
trait HasTranslations
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel(), $this->translationForeignKey());
    }

    protected function translationModel(): string
    {
        return static::class.'Translation';
    }

    protected function translationForeignKey(): string
    {
        return Str::snake(class_basename(static::class)).'_id';
    }

    public function getTranslation(?string $locale = null, ?string $fallback = null): ?Model
    {
        $locale = $locale ?: app()->getLocale();
        $translation = $this->translations->firstWhere('locale', $locale);

        if ($translation !== null) {
            return $translation;
        }

        if ($fallback !== null && $fallback !== $locale) {
            return $this->translations->firstWhere('locale', $fallback);
        }

        return null;
    }

    public function getTranslatedName(?string $locale = null, string $field = 'name'): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $translation = $this->getTranslation($locale, $fallback);

        if ($translation !== null) {
            $value = $translation->getAttribute($field);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        // 最终兜底：返回 code 或空串
        $code = $this->getAttribute('code');

        return is_string($code) ? $code : '';
    }

    /**
     * 增量同步翻译。
     *
     * @param  array<int, array<string, mixed>>  $translations
     */
    public function setTranslations(array $translations, string $field = 'name'): void
    {
        $modelClass = $this->translationModel();
        $foreignKey = $this->translationForeignKey();
        $id = $this->getKey();

        DB::transaction(function () use ($translations, $field, $modelClass, $foreignKey, $id) {
            foreach ($translations as $t) {
                $locale = $t['locale'] ?? null;
                $value = $t[$field] ?? null;

                if (! is_string($locale) || $locale === '') {
                    continue;
                }
                if (! is_string($value) || $value === '') {
                    continue;
                }

                $modelClass::updateOrCreate(
                    [$foreignKey => $id, 'locale' => $locale],
                    [$field => $value]
                );
            }
        });
    }

    /**
     * 替换全部翻译（删除当前未提供的 locale）。
     *
     * @param  array<int, array<string, mixed>>  $translations
     */
    public function syncTranslations(array $translations, string $field = 'name'): void
    {
        DB::transaction(function () use ($translations, $field) {
            $this->translations()->delete();
            $this->setTranslations($translations, $field);
        });
    }
}
